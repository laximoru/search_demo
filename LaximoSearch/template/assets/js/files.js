(function ($) {
    'use strict';

    var options;

    options = {
        uploadFileUrl: '',
        dropArea: '',
        charset: '',
        delimiter: '',
        activeIconTemplate: ''
    };

    function setOptions(params) {
        options = params;
    }

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function handleDrop(e) {
        /** @namespace e.dataTransfer */
        var dt = e.dataTransfer;
        var files = dt.files;
        handleFiles(files);
    }

    function handleFiles(files) {
        uploadFile(files[0]);
    }

    function uploadFile(file) {
        var url = options.uploadFileUrl+'&charset=' + options.charset.value + '&delimiter=' + options.delimiter.value;
        var formData = new FormData();
        formData.append('file', file);

        var formElement = document.getElementById('uploadForm');
        formElement.style.display = 'none';

        var preloader = document.getElementById('waiting');
        preloader.style.display = 'block';

        jQuery.ajax({
            url: url,
            data: formData,
            processData: false,
            contentType: false,
            type: 'POST',
            dataType: 'json',
            complete: function (data) {
                /** @namespace data.responseJSON */
                var jsonData = data.responseJSON || null;
                if (!jsonData) {
                    preloader.style.display = 'none';
                    showError(data.responseText || data.statusText + ': ' + data.status);
                } else {
                    window.location.reload();
                }
            }
        });
    }

    function showError(text) {
        var elem = document.createElement('div');
        elem.classList.add('callout');
        elem.classList.add('alert');
        elem.innerText = text.replace(/<(?:.|\n)*?>/gm, '');
        document.querySelector('#service-message').appendChild(elem);
        setTimeout(function () {
            elem.remove();
        }, 5000);
    }

    function initDropAreaEvents() {
        var dropArea = options.dropArea;
        var events = ['dragenter', 'dragover', 'dragleave', 'drop'];

        events.forEach(function (eventName) {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            dropArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropArea.classList.add('highlight');
        }

        function unhighlight() {
            dropArea.classList.remove('highlight');
        }

        dropArea.addEventListener('drop', handleDrop, false);
    }

    var methods = {
        init: function (params) {
            setOptions(params);
            initDropAreaEvents();
        },
        handleFiles: function (files) {
            handleFiles(files);
        }
    };

    $.fn.filesHelper = function (method, params) {
        var data = [];
        data.push(params);
        if (methods[method]) {
            return methods[method](params);
        }
    };
})(jQuery);