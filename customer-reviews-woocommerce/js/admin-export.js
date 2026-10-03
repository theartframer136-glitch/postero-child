jQuery(document).ready( function() {
	let crExporter = {
		init: function() {
			jQuery('#cr-export-button').on( 'click', function(event) {
				event.preventDefault();
				let startDate = new Date();
				jQuery('#cr-export-result-started').html(
					CrExportStrings.result_started.replace('%s', startDate.toLocaleDateString() + ' ' + startDate.toLocaleTimeString())
				);
				jQuery('#cr-export-reviews').hide();
				jQuery('#cr-export-progress').show();
				crExporter.exportNextChunk( 0, 0, 0, 0 );
			} );
			jQuery('#cr-export-cancel').on( 'click', function(event) {
				event.preventDefault();
				jQuery('#cr-export-cancel').data('cancelled', 1);
				jQuery('#cr-export-cancel').prop('disabled', true);
				jQuery('#cr-export-cancel').html(CrExportStrings.cancelling);
			} );
			jQuery('#cr-export-download').on('click', function(event) {
				jQuery('#cr-export-result-exported').data( 'count', 0 );
				jQuery('#cr-export-text').html( CrExportStrings.exporting_init );
				jQuery('#cr-export-progress-bar').val(0);
				jQuery("#cr-export-results").delay(3000).hide(0);
				jQuery("#cr-export-reviews").delay(3000).show(0);
			} );
		},

		exportNextChunk: function( offsetReviews, offsetReplies, totalReviews, totalReplies ) {
			if ( jQuery('#cr-export-cancel').data('cancelled') ) {
				jQuery('#cr-export-result-status').html(CrExportStrings.export_cancelled);
				crExporter.completeOrCancelledUI();
				return;
			}
			jQuery.post(
				ajaxurl,
				{
					'action': 'cr_export_chunk',
					'nonce': jQuery('#cr-export-button').data('nonce'),
					'offsetReviews': offsetReviews,
					'offsetReplies': offsetReplies,
					'totalReviews': totalReviews,
					'totalReplies': totalReplies
				},
				function( res ) {
					if ( ! res.success ) {
						jQuery('#cr-export-result-status').html(res.data.message);
						crExporter.completeOrCancelledUI();
					} else {
						res.offsetReviews = parseInt( res.offsetReviews );
						res.offsetReplies = parseInt( res.offsetReplies );
						res.totalReviews = parseInt( res.totalReviews );
						res.totalReplies = parseInt( res.totalReplies );
						// update progress
						let percentage = Math.floor( ( ( res.offsetReviews + res.offsetReplies ) / ( res.totalReviews + res.totalReplies ) ) * 100);
						jQuery('#cr-export-progress-bar').val(percentage);
						jQuery('#cr-export-text').html(
							CrExportStrings.exporting.replace( '%s', ( res.offsetReviews + res.offsetReplies ) ).replace( '%s', ( res.totalReviews + res.totalReplies ) )
						);
						// update stats
						jQuery('#cr-export-result-exported').data(
							'count',
							( res.offsetReviews + res.offsetReplies )
						);
						// either completed
						if ( res.lastChunk ) {
							crExporter.completeOrCancelledUI();
							jQuery("#cr-export-download").show();
						} else {
							// or process the next chunk
							crExporter.exportNextChunk( res.offsetReviews, res.offsetReplies, res.totalReviews, res.totalReplies );
						}
					}
				}
			);
		},

		completeOrCancelledUI: function() {
			let endDate = new Date();
			jQuery('#cr-export-result-finished').html(
				CrExportStrings.result_finished.replace('%s', endDate.toLocaleDateString() + ' ' + endDate.toLocaleTimeString())
			);
			jQuery('#cr-export-result-exported').html(
				CrExportStrings.result_exported.replace('%d', jQuery('#cr-export-result-exported').data('count'))
			);
			jQuery('#cr-export-progress').hide();
			jQuery("#cr-export-results").show();
		}
	};

	let crQnaExporter = {
		init: function() {
			jQuery('#cr-export-qna-button').on( 'click', function(event) {
				event.preventDefault();
				let startDate = new Date();
				jQuery('#cr-export-qna-result-started').html(
					CrExportStrings.result_started.replace('%s', startDate.toLocaleDateString() + ' ' + startDate.toLocaleTimeString())
				);
				jQuery('#cr-export-qna').hide();
				jQuery('#cr-export-qna-progress').show();
				crQnaExporter.exportNextChunk( 0, 0 );
			} );
			jQuery('#cr-export-qna-cancel').on( 'click', function(event) {
				event.preventDefault();
				jQuery('#cr-export-qna-cancel').data('cancelled', 1);
				jQuery('#cr-export-qna-cancel').prop('disabled', true);
				jQuery('#cr-export-qna-cancel').html(CrExportStrings.cancelling);
			} );
			jQuery('#cr-export-qna-download').on( 'click', function(event) {
				jQuery('#cr-export-qna-result-exported').data( 'qnacount', 0 );
				jQuery('#cr-export-qna-text').html( CrExportStrings.exporting_init );
				jQuery('#cr-export-qna-progress-bar').val(0);
				jQuery("#cr-export-qna-results").delay(3000).hide(0);
				jQuery("#cr-export-qna").delay(3000).show(0);
			} );
		},

		exportNextChunk: function( offset, total ) {
			if ( jQuery('#cr-export-qna-cancel').data('cancelled') ) {
				jQuery('#cr-export-qna-result-status').html(CrExportStrings.export_cancelled);
				crQnaExporter.completeOrCancelledUI();
				return;
			}
			jQuery.post(
				ajaxurl,
				{
					'action': 'cr_qna_export_chunk',
					'nonce': jQuery('#cr-export-qna-button').data('nonce'),
					'offset': offset,
					'total': total
				},
				function( res ) {
					if ( ! res.success ) {
						jQuery('#cr-export-qna-result-status').html(res.data.message);
						crQnaExporter.completeOrCancelledUI();
					} else {
						// update progress
						let percentage = Math.floor( ( res.offset / res.total ) * 100);
						jQuery('#cr-export-qna-progress-bar').val(percentage);
						jQuery('#cr-export-qna-text').html(
							CrExportStrings.exporting.replace('%s', res.offset).replace('%s', res.total)
						);
						// update stats
						jQuery('#cr-export-qna-result-exported').data(
							'qnacount',
							res.offset
						);
						// either completed
						if ( res.lastChunk ) {
							crQnaExporter.completeOrCancelledUI();
							jQuery("#cr-export-qna-download").show();
						} else {
							// or process the next chunk
							crQnaExporter.exportNextChunk( res.offset, res.total );
						}
					}
				}
			);
		},

		completeOrCancelledUI: function() {
			let endDate = new Date();
			jQuery('#cr-export-qna-result-finished').html(
				CrExportStrings.result_finished.replace('%s', endDate.toLocaleDateString() + ' ' + endDate.toLocaleTimeString())
			);
			jQuery('#cr-export-qna-result-exported').html(
				CrExportStrings.result_qna_exported.replace('%d', jQuery('#cr-export-qna-result-exported').data('qnacount'))
			);
			jQuery('#cr-export-qna-progress').hide();
			jQuery("#cr-export-qna-results").show();
		}
	}

	crExporter.init();
	crQnaExporter.init();
} );
