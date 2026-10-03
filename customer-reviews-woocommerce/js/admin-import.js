jQuery(document).ready(function() {
    var max_file_size = _wpPluploadSettings.defaults.filters.max_file_size;

    let crImporter = {
      init: function() {
        jQuery('#cr-import-cancel').on('click', function(event) {
          event.preventDefault();
          crImporter.cancelImport();
        });

        crImporter.uploader = new plupload.Uploader( {
          browse_button: document.getElementById('cr-select-button'),
          container: document.getElementById('cr-upload-container'),
          url: ajaxurl,
          multi_selection: false,
          multipart_params: {
            _wpnonce: _wpPluploadSettings.defaults.multipart_params._wpnonce,
            action: 'cr_import_upload_csv'
          },

          filters : {
            max_file_size : max_file_size,
            mime_types: [
              {
                title : "CSV files",
                extensions : "csv"
              }
            ]
          }
        } );

        crImporter.uploader.bind('postinit', function(up) {
          jQuery('#cr-upload-button').on('click', function(event) {
            event.preventDefault();
            crImporter.uploader.start();
            return false;
          });

          jQuery('#cr-upload-button').prop('disabled', true);
        });

        crImporter.uploader.init();

        crImporter.uploader.bind('QueueChanged', function(up) {
          crImporter.set_status('none', '');

          // Limit the file queue to a single file
          if ( up.files.length > 1 ) {
            var length = up.files.length;
            var to_remove = [];
            for (var i = 0; i < length - 1; i++) {
              to_remove.push(up.files[i].id);
            }
            for (var g = 0; g < to_remove.length; g++) {
              up.removeFile(to_remove[g]);
            }
          }

          // Render the list of files, for our purposes it should only display a single file
          var $file_list = jQuery('#cr-import-filelist');
          $file_list.html('');
          plupload.each(up.files, function(file) {
            $file_list.append('<div id="' + file.id + '">' + file.name + ' (' + plupload.formatSize(file.size) + ')</div>');
          });

          // If there are files in the queue, upload button is enabled, else disabled
          if (up.files.length > 0) {
            jQuery('#cr-upload-button').prop('disabled', false);
          } else {
            $file_list.html(ivoleImporterStrings.filelist_empty);
            jQuery('#cr-upload-button').prop('disabled', true);
          }
        });

        crImporter.uploader.bind('UploadProgress', function(up, file) {
          crImporter.set_status('notice', ivoleImporterStrings.uploading.replace('%s', file.percent));
        });

        crImporter.uploader.bind('UploadFile', function(up, file) {
          jQuery('#cr-select-button').prop('disabled', true);
        });

        crImporter.uploader.bind('FileUploaded', function(up, file, response) {
          var success = true, error = pluploadL10n.default_error;

          try {
            response = JSON.parse( response.response );
          } catch ( e ) {
            success = false;
          }

          if ( ! _.isObject( response ) || _.isUndefined( response.success ) ) {
            success = false;
          } else if ( ! response.success ) {
            if (_.isObject(response.data) && response.data.message) {
              error = response.data.message;
            }
            success = false;
          }

          up.refresh();
          up.removeFile(file.id);

          if ( ! success ) {
            crImporter.set_status('error', error);
            jQuery('#cr-select-button').prop('disabled', false);
            return;
          }

          crImporter.begin_import(response.data);
        });

        crImporter.uploader.bind('Error', function(up, err) {
          var error_text;
          switch (err.code) {
            case -600:
              error_text = pluploadL10n.file_exceeds_size_limit.replace('%s', err.file.name);
              break;
            default:
              error_text = pluploadL10n.default_error;
          }
          crImporter.set_status('error', error_text);
          jQuery('#cr-select-button').prop('disabled', false);
        });
      },

      set_status: function(status, text) {
        var $status = jQuery('#cr-import-status');
        $status.html(text);
        $status.removeClass('status-error status-notice');

        switch (status) {
          case 'none':
            $status.html('');
            $status.hide();
            return;
          case 'error':
            $status.addClass('status-error');
            break;
          case 'notice':
            $status.addClass('status-notice');
            break;
        }

        $status.show();
      },

      begin_import: function(importJob) {
        let startDate = new Date();
        jQuery('#cr-import-result-started').html(
          ivoleImporterStrings.result_started.replace('%s', startDate.toLocaleDateString() + ' ' + startDate.toLocaleTimeString())
        );
        jQuery('#cr-import-upload-steps').remove();
        jQuery('#cr-import-text').html(
          ivoleImporterStrings.importing.replace('%s', '0').replace('%s', importJob.num_rows)
        );
        jQuery('#cr-progress-bar').data('numreviews', importJob.num_rows);
        jQuery('#cr-import-progress').show();
        jQuery('#cr-import-result-details > *:not("h4")').remove();
        //
        crImporter.importNextChunk( importJob.offset, 0, importJob.progress_id );
      },

      importNextChunk: function( offset, lastLine, progressID ) {
        if ( jQuery('#cr-import-cancel').data('cancelled') ) {
          jQuery('#cr-import-result-status').html(ivoleImporterStrings.upload_cancelled);
          crImporter.completeOrCancelledUI();
          return;
        }
        jQuery.post(
          ajaxurl,
          {
            action: 'cr_import_chunk',
            cr_nonce: jQuery('.cr-import-container').data('nonce'),
            offset: offset,
            lastLine: lastLine,
            progressID: progressID
          },
          function( res ) {
            if ( ! res.success ) {
              jQuery('#cr-import-result-status').html(res.data.message);
              crImporter.completeOrCancelledUI();
              jQuery('#cr-import-results p, #cr-import-results div').hide();
            } else {
              // update progress
              let percentage = Math.floor( ( res.lastLine / jQuery('#cr-progress-bar').data('numreviews') ) * 100);
              jQuery('#cr-progress-bar').val(percentage);
              jQuery('#cr-import-text').html(
                ivoleImporterStrings.importing.replace('%s', res.lastLine).replace('%s', jQuery('#cr-progress-bar').data('numreviews'))
              );
              // update stats
              jQuery('#cr-import-result-rev-imported').data(
                'count',
                jQuery('#cr-import-result-rev-imported').data('count') + res.data.rev.imported
              );
              jQuery('#cr-import-result-rep-imported').data(
                'count',
                jQuery('#cr-import-result-rep-imported').data('count') + res.data.rep.imported
              );
              jQuery('#cr-import-result-rev-skipped').data(
                'count',
                jQuery('#cr-import-result-rev-skipped').data('count') + res.data.rev.skipped
              );
              jQuery('#cr-import-result-rep-skipped').data(
                'count',
                jQuery('#cr-import-result-rep-skipped').data('count') + res.data.rep.skipped
              );
              jQuery('#cr-import-result-errors').data(
                'count',
                jQuery('#cr-import-result-errors').data('count') + res.data.errors
              );
              if ( res.data.error_list && 0 < res.data.error_list.length ) {
                jQuery('#cr-import-result-details').append(
                  res.data.error_list.join('<br>') + '<br>'
                );
              }
              // either completed
              if ( res.lastChunk ) {
                crImporter.completeOrCancelledUI();
              } else {
                // or process the next chunk
                crImporter.importNextChunk( res.offset, res.lastLine, res.progressID );
              }
            }
          }
        );
      },

      cancelImport: function() {
        jQuery('#cr-import-cancel').data('cancelled', 1);
        jQuery('#cr-import-cancel').prop('disabled', true);
        jQuery('#cr-import-cancel').html(ivoleImporterStrings.cancelling);
      },

      completeOrCancelledUI: function() {
        let endDate = new Date();
        jQuery('#cr-import-result-finished').html(
          ivoleImporterStrings.result_finished.replace('%s', endDate.toLocaleDateString() + ' ' + endDate.toLocaleTimeString())
        );
        jQuery('#cr-import-result-rev-imported').html(
          ivoleImporterStrings.result_imported.replace('%d', jQuery('#cr-import-result-rev-imported').data('count'))
        );
        jQuery('#cr-import-result-rep-imported').html(
          ivoleImporterStrings.result_rep_imported.replace('%d', jQuery('#cr-import-result-rep-imported').data('count'))
        );
        jQuery('#cr-import-result-rev-skipped').html(
          ivoleImporterStrings.result_skipped.replace('%d', jQuery('#cr-import-result-rev-skipped').data('count'))
        );
        jQuery('#cr-import-result-rep-skipped').html(
          ivoleImporterStrings.result_rep_skipped.replace('%d', jQuery('#cr-import-result-rep-skipped').data('count'))
        );
        jQuery('#cr-import-result-errors').html(
          ivoleImporterStrings.result_errors.replace('%d', jQuery('#cr-import-result-errors').data('count'))
        );
        jQuery('#cr-import-progress').hide();
        jQuery('#cr-import-results').show();
      }
    };

    let crQnaImporter = {
      progress_id: null,

      init: function() {
          jQuery('#cr-qna-import-cancel').on('click', function(event) {
              event.preventDefault();
              crQnaImporter.cancelImport();
          });
          crQnaImporter.uploader = new plupload.Uploader( {
              browse_button: document.getElementById('cr-qna-select-button'),
              container: document.getElementById('cr-qna-upload-container'),
              url: ajaxurl,
              multi_selection: false,
              multipart_params: {
                  _wpnonce: _wpPluploadSettings.defaults.multipart_params._wpnonce,
                  action: 'cr_import_qna_upload_csv'
              },
              filters : {
                  max_file_size : max_file_size,
                  mime_types: [
                      {
                          title : "CSV files",
                          extensions : "csv"
                      }
                  ]
              }
          } );

          crQnaImporter.uploader.bind('postinit', function(up) {
              jQuery('#cr-qna-upload-button').on('click', function(event) {
                  event.preventDefault();
                  crQnaImporter.uploader.start();
                  return false;
              });
              jQuery('#cr-qna-upload-button').prop('disabled', true);
          });

          crQnaImporter.uploader.init();

          crQnaImporter.uploader.bind('QueueChanged', function(up) {
              crQnaImporter.set_qna_status('none', '');

              // Limit the file queue to a single file
              if (up.files.length > 1) {
                  var length = up.files.length;
                  var to_remove = [];
                  for (var i = 0; i < length - 1; i++) {
                      to_remove.push(up.files[i].id);
                  }

                  for (var g = 0; g < to_remove.length; g++) {
                      up.removeFile(to_remove[g]);
                  }
              }

              // Render the list of files, for our purposes it should only display a single file
              let file_list = jQuery('#cr-qna-import-filelist');
              file_list.html('');
              plupload.each(up.files, function(file) {
                  file_list.append('<div id="' + file.id + '">' + file.name + ' (' + plupload.formatSize(file.size) + ')</div>');
              });

              // If there are files in the queue, upload button is enabled, else disabled
              if (up.files.length > 0) {
                  jQuery('#cr-qna-upload-button').prop('disabled', false);
              } else {
                  file_list.html(ivoleImporterStrings.filelist_empty);
                  jQuery('#cr-qna-upload-button').prop('disabled', true);
              }
          });

          crQnaImporter.uploader.bind('UploadProgress', function(up, file) {
              crQnaImporter.set_qna_status('notice', ivoleImporterStrings.uploading.replace('%s', file.percent));
          });

          crQnaImporter.uploader.bind('UploadFile', function(up, file) {
              jQuery('#cr-qna-select-button').prop('disabled', true);
          });

          crQnaImporter.uploader.bind('FileUploaded', function(up, file, response) {
              var success = true, error = pluploadL10n.default_error;

              try {
                  response = JSON.parse( response.response );
              } catch ( e ) {
                  success = false;
              }

              if ( ! _.isObject( response ) || _.isUndefined( response.success ) ) {
                  success = false;
              } else if ( ! response.success ) {
                  if ( _.isObject(response.data) && response.data.message ) {
                      error = response.data.message;
                  }
                  success = false;
              }

              up.refresh();
              up.removeFile(file.id);

              if ( ! success ) {
                  crQnaImporter.set_qna_status('error', error);
                  jQuery('#cr-qna-select-button').prop('disabled', false);
                  return;
              }

              crQnaImporter.beginImport( response.data );
          } );

          crQnaImporter.uploader.bind('Error', function(up, err) {
              var error_text;
              switch (err.code) {
                  case -600:
                      error_text = pluploadL10n.file_exceeds_size_limit.replace('%s', err.file.name);
                      break;
                  default:
                      error_text = pluploadL10n.default_error;
              }
              crQnaImporter.set_qna_status('error', error_text);
              jQuery('#cr-qna-select-button').prop('disabled', false);
          } );
      },

      set_qna_status: function(status, text) {
          let statusEl = jQuery('#cr-qna-import-status');
          statusEl.html(text);
          statusEl.removeClass('status-error status-notice');
          switch (status) {
              case 'none':
                  statusEl.html('');
                  statusEl.hide();
                  return;
              case 'error':
                  statusEl.addClass('status-error');
                  break;
              case 'notice':
                  statusEl.addClass('status-notice');
                  break;
          }
          statusEl.show();
      },

      beginImport: function(importJob) {
          let startDate = new Date();
          jQuery('#cr-qna-import-result-started').html(
            ivoleImporterStrings.result_started.replace('%s', startDate.toLocaleDateString() + ' ' + startDate.toLocaleTimeString())
          );
          jQuery('#cr-qna-import-upload-steps').remove();
          jQuery('#cr-qna-import-text').html(
            ivoleImporterStrings.importing.replace('%s', '0').replace('%s', importJob.num_rows)
          );
          jQuery('#cr-qna-progress-bar').data('numreviews', importJob.num_rows);
          jQuery('#cr-qna-import-progress').show();
          jQuery('#cr-qna-import-result-details > *:not("h4")').remove();
          //
          crQnaImporter.importNextChunk( importJob.offset, 0, importJob.progress_id );
      },

      importNextChunk: function( offset, lastLine, progressID ) {
        if ( jQuery('#cr-qna-import-cancel').data('cancelled') ) {
          jQuery('#cr-qna-import-result-status').html(ivoleImporterStrings.upload_cancelled);
          crQnaImporter.completeOrCancelledUI();
          return;
        }
        jQuery.post(
            ajaxurl,
            {
                action: 'cr_qna_import_chunk',
                cr_nonce: jQuery('.cr-import-container').data('nonce'),
                offset: offset,
                lastLine: lastLine,
                progressID: progressID
            },
            function( res ) {
              if ( ! res.success ) {
                jQuery('#cr-qna-import-result-status').html(res.data.message);
                crQnaImporter.completeOrCancelledUI();
                jQuery('#cr-qna-import-results p, #cr-qna-import-results div').hide();
              } else {
                // update progress
                let percentage = Math.floor( ( res.lastLine / jQuery('#cr-qna-progress-bar').data('numreviews') ) * 100);
                jQuery('#cr-qna-progress-bar').val(percentage);
                jQuery('#cr-qna-import-text').html(
                  ivoleImporterStrings.importing.replace('%s', res.lastLine).replace('%s', jQuery('#cr-qna-progress-bar').data('numreviews'))
                );
                // update stats
                jQuery('#cr-qna-import-result-que-imported').data(
                  'qnacount',
                  jQuery('#cr-qna-import-result-que-imported').data('qnacount') + res.data.que.imported
                );
                jQuery('#cr-qna-import-result-ans-imported').data(
                  'qnacount',
                  jQuery('#cr-qna-import-result-ans-imported').data('qnacount') + res.data.ans.imported
                );
                jQuery('#cr-qna-import-result-que-skipped').data(
                  'qnacount',
                  jQuery('#cr-qna-import-result-que-skipped').data('qnacount') + res.data.que.skipped
                );
                jQuery('#cr-qna-import-result-ans-skipped').data(
                  'qnacount',
                  jQuery('#cr-qna-import-result-ans-skipped').data('qnacount') + res.data.ans.skipped
                );
                jQuery('#cr-qna-import-result-errors').data(
                  'qnacount',
                  jQuery('#cr-qna-import-result-errors').data('qnacount') + res.data.errors
                );
                if ( res.data.error_list && 0 < res.data.error_list.length ) {
                  jQuery('#cr-qna-import-result-details').append(
                    res.data.error_list.join('<br>') + '<br>'
                  );
                }
                // either completed
                if ( res.lastChunk ) {
                  crQnaImporter.completeOrCancelledUI();
                } else {
                  // or process the next chunk
                  crQnaImporter.importNextChunk( res.offset, res.lastLine, res.progressID );
                }
              }
            }
        );
      },

      cancelImport: function() {
        jQuery('#cr-qna-import-cancel').data('cancelled', 1);
        jQuery('#cr-qna-import-cancel').prop('disabled', true);
        jQuery('#cr-qna-import-cancel').html(ivoleImporterStrings.cancelling);
      },

      completeOrCancelledUI: function() {
        let endDate = new Date();
        jQuery('#cr-qna-import-result-finished').html(
          ivoleImporterStrings.result_finished.replace('%s', endDate.toLocaleDateString() + ' ' + endDate.toLocaleTimeString())
        );
        jQuery('#cr-qna-import-result-que-imported').html(
          ivoleImporterStrings.result_q_imported.replace('%d', jQuery('#cr-qna-import-result-que-imported').data('qnacount'))
        );
        jQuery('#cr-qna-import-result-ans-imported').html(
          ivoleImporterStrings.result_a_imported.replace('%d', jQuery('#cr-qna-import-result-ans-imported').data('qnacount'))
        );
        jQuery('#cr-qna-import-result-que-skipped').html(
          ivoleImporterStrings.result_q_skipped.replace('%d', jQuery('#cr-qna-import-result-que-skipped').data('qnacount'))
        );
        jQuery('#cr-qna-import-result-ans-skipped').html(
          ivoleImporterStrings.result_a_skipped.replace('%d', jQuery('#cr-qna-import-result-ans-skipped').data('qnacount'))
        );
        jQuery('#cr-qna-import-result-errors').html(
          ivoleImporterStrings.result_errors.replace('%d', jQuery('#cr-qna-import-result-errors').data('qnacount'))
        );
        jQuery('#cr-qna-import-progress').hide();
        jQuery('#cr-qna-import-results').show();
      }
    };

    crImporter.init();
    crQnaImporter.init();
})
