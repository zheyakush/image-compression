(function($) {
    if (typeof window.Compressor === "undefined") {
        window.Compressor = {};
    }

    var hasAjax = false;
    var isBreak = false;


    var _resetPageElem = function() {
        isBreak = true;
        hasAjax = false;
        $(".table-result").addClass("hide");
        $(".table-result tbody tr").remove();
        $(".progress").addClass("hide");
        $("#btnCompress").removeClass("hide");
        $("#btnBreak").addClass("hide");
        $("#btnLog").addClass("hide");
        $("#btnArchive").addClass("hide");
        $("#processedFiles").text(0);
    }

    window.Compressor.addAdditionalKey = function() {
        var tmpl = $("#apiKeyTpl").clone();
        tmpl.removeClass("hide")
            .removeAttr("id")
            .find("input")
            .attr("name", "apiKey[]");
        tmpl.insertBefore($("#sourcePath").parent());
    };

    window.Compressor.removeAdditionalKey = function(elem) {
        $(elem).parent('div').remove();
    };

    window.Compressor.addAdditionalPath = function() {
        var tmpl = $("#sourcePathTpl").clone();
        tmpl.removeClass("hide")
            .removeAttr("id")
            .find("input")
            .attr("name", "sourcePath[]");
        tmpl.insertBefore($("button:first"));
    };

    window.Compressor.removePath = function(elem) {
        $(elem).parent('div').remove();
    };

    window.Compressor.submit = function(btn) {
        var form = $(btn).parent("form");
        $(btn).addClass("hide");
        $("#btnBreak").removeClass("hide");
        var changeStatus = function($data) {
            $("#countFiles").text($data["files"]);
            $("#processedFiles").text($data["processedFiles"]);
            $(".progress").removeClass("hide");
            $(".progress-bar")
                .attr("aria-valuenow", $data["progress"])
                .width($data["progress"] + "%");
        };
        var updateTable = function(data) {
            if (typeof data["details"] === "undefined") {
                return false;
            } else {
                var details = data["details"];
                $(".table-result").removeClass("hide");
            }
            var nextRowIndex = $(".table-result tr").length;
            var rowHTML = '<tr><th scope="row">' + nextRowIndex + '</th><td>' + details['filePath'] + '</td><td>' + details['sizeBefore'] + '</td><td>' + details['sizeAfter'] + '</td><td>' + details['compression'] + '%</td></tr>';
            $(".table-result tbody").append(rowHTML);
        };
        isBreak = false;
        hasAjax = false;

        $.ajax({
            method: form.attr("method"),
            data: form.serialize(),
            success: function(data) {
                if (typeof data['error'] !== "undefined") {
                    alert(data['error']);
                    return false;
                } else {
                    changeStatus(data);
                    var interval = setInterval(function() {
                        if (!hasAjax && !isBreak) {
                            $.ajax({
                                method: form.attr("method"),
                                data: {"type": "progress"},
                                beforeSend: function() {
                                    hasAjax = true;
                                },
                                complete: function() {
                                    hasAjax = false;
                                },
                                success: function(data) {
                                    if (typeof data['error'] !== "undefined") {
                                        clearInterval(interval);
                                        alert(data['error']);
                                        _resetPageElem();
                                        return false;
                                    } else {
                                        if (isBreak) {
                                            return false;
                                        }
                                        changeStatus(data);
                                        updateTable(data);
                                        if (data["progress"] === 100 || isBreak) {
                                            clearInterval(interval);
                                            setTimeout(function() {
                                                $(".progress-bar").removeClass("active");
                                                $("#btnBreak").addClass("hide");
                                                $("#btnLog").removeClass("hide");
                                                $("#btnArchive").removeClass("hide");
                                            }, 500);
                                        }
                                    }
                                }
                            });
                        }
                    }, 100);
                }
            }
        });
        return false;
    };

    window.Compressor.downloadLog = function() {
        var form = $('<form><input type="hidden" name="type" value="log" /></form>').attr('action', './').attr('method', 'post');
        form.appendTo('body').submit();
    };


    window.Compressor.downloadArchive = function() {
        var form = $('<form><input type="hidden" name="type" value="archive" /></form>').attr('action', './').attr('method', 'post');
        form.appendTo('body').submit();
    };

    window.Compressor.break = function() {
        _resetPageElem();
        $.ajax({
            method: "POST",
            data: {"type": "break"}
        });
    };

})(jQuery);
