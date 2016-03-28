<?php

require_once("vendor/autoload.php");

/**
 * Class ImageCompression
 *
 * @copyright    Copyright (c) 2016 (eyakushdev@gmail.com)
 * @author       Eugene Yakush (eyakushdev@gmail.com)
 * @license      http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ImageCompression
{
    /**
     * @var array
     */
    protected $_apiKeys = [];
    /**
     * @var int
     */
    protected $_activeKeyIndex = 0;
    /**
     * @var array
     */
    protected $_sourcePath = [];
    /**
     * @var array
     */
    protected $_availableExt = ["*.png", "*.jpg"];
    /**
     * @var string
     */
    protected $_resultPath = "../result";
    /**
     * @var string
     */
    protected $_logPath = "../log";
    /**
     * @var string
     */
    protected $_archivePath = "../archive";
    /**
     * @var array
     */
    protected $_originFiles = [];
    /**
     * @var array
     */
    protected $_compressedFiles = [];
    /**
     * @var array
     */
    protected $_logRows = [];
    /**
     * @var resource
     */
    protected $_logFile;
    /**
     * @var int
     */
    protected $_processedFilesCount = 0;

    /**
     * ImageCompression constructor.
     */
    public function __construct()
    {
        if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
            return null;
        }
        if (isset($_SESSION['compressorObj']) && isset($_POST["type"])) {
            foreach ($_SESSION['compressorObj'] as $field => $data) {
                $this->$field = $data;
            }

            switch ($_POST["type"]) {
                case "progress":
                    $this->_runCompression();
                    break;
                case "log":
                    $this->_downloadLogFile();
                    break;
                case "archive":
                    $this->_downloadArchiveFile();
                    break;

            }

        } else {
            $this->_apiKeys = (array)$_POST["apiKey"];
            $this->_sourcePath = (array)$_POST["sourcePath"];
            $this->_prepareFilesList();
            $_SESSION['compressorObj'] = $this;

            $this->_response([
                "files"    => count($this->_originFiles),
                "progress" => $this->_getCurrentProgress()
            ]);
        }
    }

    /**
     * Read array of folders and collects files from theirs
     */
    protected function _prepareFilesList()
    {
        $files = [];
        foreach ($this->_availableExt as $ext) {
            foreach ($this->_sourcePath as $path) {
                $files += $this->_globRecursive(rtrim($path, "/") . DIRECTORY_SEPARATOR . $ext);
            }
        }

        foreach ($files as $filePath) {
            $data = pathinfo($filePath);
            $this->_originFiles[] = [
                "filePath" => $filePath,
                "path"     => $data["dirname"],
                "fileName" => $data["basename"],
                "size"     => filesize($filePath)
            ];
        }
    }

    /**
     * Reads  files in folders recursively
     *
     * @param $pattern
     * @param int $flags
     * @return array
     */
    protected function _globRecursive($pattern, $flags = 0)
    {
        $filesList = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $filesList = array_merge($filesList, $this->_globRecursive($dir . '/' . basename($pattern), $flags));
        }

        return $filesList;
    }

    /**
     * Returns bytes in human format
     * @param $size
     * @param int $precision
     * @return string
     */
    protected function _formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['', 'k', 'M', 'G', 'T'];

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    /**
     * Checks folder on exists and create one if folder does not exists
     *
     * @param $path
     */
    protected function _checkFolder($path)
    {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
    }

    protected function _runCompression()
    {
        $fileInfo = $this->_originFiles[$this->_processedFilesCount];
        $resultPath = $this->_resultPath . $fileInfo['path'];
        $this->_checkFolder($resultPath);

        $resultFilePath = $resultPath . DIRECTORY_SEPARATOR . $fileInfo["fileName"];
        $this->_compress($this->_apiKeys[$this->_activeKeyIndex], $fileInfo, $resultFilePath);

        $compressedFile = [
            "filePath" => $resultFilePath,
            "path"     => $resultPath,
            "fileName" => $fileInfo["fileName"],
            "size"     => filesize($resultFilePath)
        ];
        $this->_compressedFiles[] = $compressedFile;

        $this->_processedFilesCount++;
        $this->_log("{$fileInfo['filePath']};{$fileInfo['size']};" .
            $this->_formatBytes($fileInfo['size']) .
            ";" . @filesize($resultFilePath) . ";" . $this->_formatBytes(@filesize($resultFilePath)));

        $compression = number_format((1 - (@filesize($resultFilePath) / $fileInfo['size'])) * 100, 2);
        $this->_response([
            "files"          => count($this->_originFiles),
            "progress"       => $this->_getCurrentProgress(),
            "processedFiles" => $this->_getPrecessedFilesCount(),
            "details"        => [
                "filePath"    => $fileInfo['filePath'],
                "sizeBefore"  => $this->_formatBytes($fileInfo['size']),
                "sizeAfter"   => $this->_formatBytes(@filesize($resultFilePath)),
                "compression" => $compression
            ]
        ]);
    }

    /**
     * @param array $data
     */
    protected function _response($data = [])
    {
        $_SESSION['compressorObj'] = $this;
        header('Content-Type: application/json');
        echo json_encode($data);

        exit;
    }

    /**
     * @return int
     */
    protected function _getCurrentProgress()
    {
        $result = ceil(($this->_getPrecessedFilesCount() / count($this->_originFiles)) * 100);
        if ($result === 100 && $this->_getPrecessedFilesCount() < count($this->_originFiles)) {
            $result = 99;
        }

        return $result;
    }

    /**
     * @return int
     */
    protected function _getPrecessedFilesCount()
    {
        return (int)$this->_processedFilesCount;
    }

    /**
     * @param $apiKey
     * @param $fileInfo
     * @param $resultFilePath
     * @return bool
     */
    protected function _compress($apiKey, $fileInfo, $resultFilePath)
    {
        try {

            \Tinify\setKey($apiKey);
            $source = \Tinify\fromFile($fileInfo["filePath"]);
            $source->toFile($resultFilePath);

            return true;
        } catch (Exception $e) {
            $this->_activeKeyIndex++;
            $_SESSION['compressorObj'] = $this;
            $this->_compress($this->_apiKeys[$this->_activeKeyIndex], $fileInfo, $resultFilePath);

            exit;
        }
    }

    /**
     * @param $string
     */
    protected function _log($string)
    {
        $this->_logRows[] = $string;
        if (is_null($this->_logFile)) {
            $this->_checkFolder($this->_logPath);
            $this->_logFile = $this->_logPath . DIRECTORY_SEPARATOR . "compression_" . time() . ".csv";
            if ($f = fopen($this->_logFile, 'a+')) {
                $header = "File;Origin bytes;Origin size;Compressed bytes;Compressed size;" . PHP_EOL;
                fwrite($f, $header);
            }
        }

        if (count($this->_logRows) > 100) {
            if ($f = fopen($this->_logFile, 'a+')) {
                fwrite($f, join(PHP_EOL, $this->_logRows));
                $this->_logRows = [];
            }
        }

        if ($this->_getPrecessedFilesCount() === count($this->_originFiles)) {
            if ($f = fopen($this->_logFile, 'a+')) {
                fwrite($f, join(PHP_EOL, $this->_logRows));
                $this->_logRows = [];
                fclose($f);
            }
        }
    }

    /**
     * @source http://stackoverflow.com/questions/1334613/how-to-recursively-zip-a-directory-in-php?answertab=active#tab-top
     * @param $source
     * @param $destination
     * @return bool
     */
    protected function _zip($source, $destination)
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }

        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
            return false;
        }

        $source = str_replace('\\', '/', realpath($source));

        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);

                // Ignore "." and ".." folders
                if (in_array(substr($file, 1 + strrpos($file, '/')), ['.', '..']))
                    continue;

                $file = realpath($file);

                if (is_dir($file) === true) {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                } else if (is_file($file) === true) {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        } else if (is_file($source) === true) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }

        return $zip->close();
    }

    /**
     * @param string $file
     */
    protected function _prepareHeadersForDownloading($file)
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
    }

    /**
     *
     */
    protected function _downloadLogFile()
    {
        if (file_exists($this->_logFile)) {
            $this->_prepareHeadersForDownloading($this->_logFile);
            // читаем файл и отправляем его пользователю
            if ($fd = fopen($this->_logFile, 'r')) {
                while (!feof($fd)) {
                    print fread($fd, 1024);
                }
                fclose($fd);
            }
            exit;
        }
    }

    /**
     *
     */
    protected function _downloadArchiveFile()
    {
        $archiveFile = $this->_archivePath . DIRECTORY_SEPARATOR . basename($this->_logFile) . ".zip";
        $this->_zip(trim($this->_resultPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $archiveFile);
        if (file_exists($archiveFile)) {
            $this->_prepareHeadersForDownloading($archiveFile);
            // читаем файл и отправляем его пользователю
            readfile($archiveFile);

            exit;
        }
    }
}
