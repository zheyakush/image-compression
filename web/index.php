<?php
ini_set('display_errors', 1);
require_once "../ImageCompression.php";
session_start();
$compressor = new ImageCompression();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css" type="text/css">
    <link rel="stylesheet" href="css/style.css" type="text/css">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <script src="js/main.js"></script>
</head>
<body>
<div class="container">
    <h1 class="text-center">Image compression using TinyPNG service</h1>
    <form method="POST">
        <div class="form-group">
            <label for="apiKey">API Key </label>
            <span class="glyphicon glyphicon-plus text-success" onclick="Compressor.addAdditionalKey();"></span>
            <input type="text" class="form-control" name="apiKey[]" id="apiKey"
                   placeholder="API Key" />
        </div>
        <div class="input-group form-group hide" id="apiKeyTpl">
            <input type="text" class="form-control" placeholder="API Key" value=""/>
            <span class="btn btn-default input-group-addon" onclick="Compressor.removeAdditionalKey(this);">-</span>
        </div>
        <div class="form-group">
            <label for="sourcePath">Path to sources</label>
            <span class="glyphicon glyphicon-plus text-success" onclick="Compressor.addAdditionalPath();"></span>
            <input type="text" class="form-control" id="sourcePath" placeholder="Path to sources"
                   name="sourcePath" />
        </div>
        <div class="input-group form-group hide" id="sourcePathTpl">
            <input type="text" class="form-control" placeholder="Path to sources" value=""/>
            <span class="btn btn-default input-group-addon" onclick="Compressor.removePath(this);">-</span>
        </div>
        <button type="button" class="btn btn-default" id="btnCompress" onclick="window.Compressor.submit(this)">Compress</button>
        <button type="button" class="btn btn-danger hide" id="btnBreak" onclick="window.Compressor.break(this)">Break</button>

        <button type="button" class="btn btn-success hide" id="btnLog" onclick="window.Compressor.downloadLog()">Log file</button>
        <button type="button" class="btn btn-success hide" id="btnArchive" onclick="window.Compressor.downloadArchive()">Archive file</button>
    </form>

    <div class="progress hide" style="margin-top: 20px">
        <div class="label label-info status">
            <span id="processedFiles">0</span> / <span id="countFiles"></span>
        </div>
        <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="70" aria-valuemin="0" aria-valuemax="0"
             style="width: 0%;">
        </div>
    </div>
    <table class="table table-striped table-result hide">
        <thead>
        <tr>
            <th>#</th>
            <th>File Path</th>
            <th>Size before</th>
            <th>Size after</th>
            <th>Compression</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
</body>
</html>
