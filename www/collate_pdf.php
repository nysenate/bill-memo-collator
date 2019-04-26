<?php

// Some constants for us.
$output_dir = '/data/billmemos';
$log_dir = '/var/log/billmemos';
$log_file = $log_dir.'/collate_pdf.log';
$exec_script = dirname(__FILE__).'/../scripts/collate_bill_memos.sh';

if (!empty($_FILES['userfiles']) && !empty($_POST['process'])) {
  /*
    Do the processing here.  The loop below demonstrates how
    the files are enumerated in PHP.
  */
  $output_files = [];
  $messages = [];
  $success = true;
  $exec_out = [];   // output from the shell command as an array of lines
  $exec_ret = 0;    // return code from the shell command
  $userfiles = $_FILES['userfiles'];

  if (isset($userfiles['name']) && count($userfiles['name']) > 0) {
    foreach ($userfiles['name'] as $idx => $fname) {
      if ($fname) {
        $fsize = $userfiles['size'][$idx];
        $fpath = $userfiles['tmp_name'][$idx];

        // Messages for debugging.
        $messages[] = "Received file #".($idx+1).": $fname ($fsize)";

        // Process the files.
        $out_fname = preg_replace('/[.]pdf$/', '.collated.pdf', $fname);
        $out_fpath = realpath($output_dir)."/$out_fname";
        $exec_str = "$exec_script \"$fpath\" --output-file \"$out_fpath\" 2>&1";
        $exec_out = [];

        exec("$exec_str", $exec_out, $exec_ret);

        // Log the output from the shell script.
        $exec_out = implode("\n", $exec_out)."\n";
        file_put_contents($log_file, $exec_out, FILE_APPEND | LOCK_EX);

        if ($exec_ret == 0) {
          // Add the final processed file location to the output list.
          $output_files[] = "/collated/$out_fname";
          $messages[] = "File $fname processed successfully; collated file is $out_fname";
        }
        else {
          $messages[] = "exec() failed; error code=$exec_ret";
          $messages[] = "Command line:\n$exec_str";
          $messages[] = "Command output:\n$exec_out";
        }
      }
    }
  }

  if (!count($output_files)) {
    $success = false;
    $messages[] = 'No files received.';
  }

  $result = (object) [
    'files' => $output_files,
    'messages' => $messages,
    'success' => $success,
  ];

  $json = json_encode($result);
  if ($json !== false) {
    echo $json;
    exit(0);
  }
  else {
    exit(1);
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>PDF Processor</title>
  <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
  <script type="text/javascript">
    $(function () {
      (function (jQuery, document, window, undefined) {
        var isAdvancedUpload = function () {
          var div = document.createElement('div');
          return (('draggable' in div) || ('ondragstart' in div && 'ondrop' in div)) && 'FormData' in window && 'FileReader' in window;
        }();
        var $form = $('.box'), $input = $('.box__file');

        if (isAdvancedUpload) {
          $form.addClass('has-advanced-upload');

          var droppedFiles = false;

          $form.on('drag dragstart dragend dragover dragenter dragleave drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
          })
            .on('dragover dragenter', function () {
              $form.addClass('is-dragover');
            })
            .on('dragleave dragend drop', function () {
              $form.removeClass('is-dragover');
            })
            .on('drop', function (e) {
              droppedFiles = e.originalEvent.dataTransfer.files;
              if (droppedFiles) {
                $input.hide();
                var $drag = $('.box__dragged').html('');
                for (var x=0; x < droppedFiles.length; x++) {
                  $drag.append('<li>' + droppedFiles.item(x).name + '</li>');
                }
              }
            });
        }

        $form.on('submit', function (e) {
          if ($form.hasClass('is-uploading')) {
            return false;
          }

          $form.addClass('is-uploading').removeClass('is-error');

          if (isAdvancedUpload) {
            e.preventDefault();

            var ajaxData = new FormData($form.get(0));

            if (droppedFiles) {
              ajaxData.delete($input.attr('name'));
              $.each(droppedFiles, function (i, file) {
                ajaxData.append($input.attr('name'), file, file.name);
              });
            }

            $.ajax({
              url: $form.attr('action'),
              type: $form.attr('method'),
              data: ajaxData,
              dataType: 'json',
              cache: false,
              contentType: false,
              processData: false,
              complete: function (d) {
                $form.removeClass('is-uploading');
                $r = $('#results').html('');
                d.responseJSON.files.forEach(function(v,i) {
                  $r.append('<li><a href="' + v + '">' + v + '</a></li>');
                });
                $r.append('<h3>Messages</h3>');
                d.responseJSON.messages.forEach(function(v,i) {
                  $r.append('<pre>' + v + '</pre>');
                });
              },
              success: function (data) {
                $form.addClass(data.success == true ? 'is-success' : 'is-error');
              },
              error: function () {
                // Log the error, show an alert, whatever works for you
                alert('there was an error');
              }
            });
          }
          else {
            var iframeName = 'uploadiframe' + new Date().getTime();
            $iframe = $('<iframe name="' + iframeName + '" style="display: none;"></iframe>');

            $('body').append($iframe);
            $form.attr('target', iframeName);

            $iframe.one('load', function () {
              var data = JSON.parse($iframe.contents().find('body').text());
              $form
                .removeClass('is-uploading')
                .addClass(data.success == true ? 'is-success' : 'is-error')
                .removeAttr('target');
              if (!data.success) {
                $errorMsg.text(data.error);
              }
              $form.removeAttr('target');
              $iframe.remove();
            });
          }
        });
      })(jQuery, document, window);
    });
  </script>
  <style type="text/css">
    div {
      max-width: 1000px;
    }

    .box__dragndrop,
    .box__uploading,
    .box__success,
    .box__error {
      display: none;
    }

    .box {
      font-size: 1.25rem;
      background-color: #c8dadf;
      position: relative;
      padding: 100px 20px;
    }

    .box.has-advanced-upload {
      outline: 2px dashed #92b0b3;
      outline-offset: -10px;
      -webkit-transition: outline-offset .15s ease-in-out, background-color .15s linear;
      transition: outline-offset .15s ease-in-out, background-color .15s linear;
    }

    .box.has-advanced-upload .box__dragndrop {
      display: inline;
    }

    .box.is-dragover {
      background-color: grey;
    }

    .box.is-uploading .box__input {
      visibility: none;
    }

    .box.is-uploading .box__uploading {
      display: block;
    }
  </style>
</head>
<body>
<div>
  <form class="box" method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="process" value="1"/>
    <div class="box__input">
      <input class="box__file" type="file" name="userfiles[]" id="file"
             data-multiple-caption="{count} files selected" multiple/>
      <label for="file"><strong>Choose a file</strong><span
            class="box__dragndrop"> or drag it here.</span></label>
      <button class="box__button" type="submit">Upload</button>
      <div class="box__dragged"></div>
    </div>
    <div class="box__uploading">Uploading&hellip;</div>
    <div class="box__success">Done!</div>
    <div class="box__error">Error! <span></span>.</div>
  </form>
  <h1>RESULTS:</h1>
  <pre id="results"></pre>
</div>
</body>
</html>
