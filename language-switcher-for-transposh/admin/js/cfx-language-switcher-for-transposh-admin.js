(function ($) {
	"use strict";
	$(function () {
		var editor, editorSettings, isDirty = false;

		function initEditor() {
			editorSettings = wp.codeEditor.defaultSettings
				? _.clone(wp.codeEditor.defaultSettings)
				: {};
			editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
				autoRefresh: true,
				indentUnit: 4,
				tabSize: 4,
				mode: "css",
			});
			editor = wp.codeEditor.initialize(
				$("#code_editor_page_css"),
				editorSettings
			);
			// check for changes in the editor
			editor.codemirror.on('change', function (cMirror) {
				isDirty = true;
				// Update original textarea (necessary for form submission)
				cMirror.save();
			});
		}

		function load_style() {
			var stylesheet = 'lsft.css';
			initEditor();
			var data = {
				action: "load_style",
				stylesheet: stylesheet,
			};
			$.post(ajaxurl, data, function (result) {
				if ("" !== result) {
					if (editor) {
						editor.codemirror.toTextArea();
						$("#code_editor_page_css").val(result);
						editor = wp.codeEditor.initialize(
							$("#code_editor_page_css"),
							editorSettings
						);
					}
				}
			});
		}

		$('#reset-css').on('click', function (e) {
			e.preventDefault();
			if (confirm("Are you sure? This will delete your custom CSS and restore the default file.")) {
				var data = {
					action: "load_style",
					stylesheet: 'lsft.css',
				};
				$.post(ajaxurl, data, function (result) {
					if (result) {
						editor.codemirror.setValue(result);
						isDirty = false;
						$('#custom-style-form').submit();
					}
				});
			}
		});

		function load_initial_content() {
			// Se la textarea ha già del contenuto (caricato da PHP dal DB), non chiamare AJAX
			if ($("#code_editor_page_css").val().trim() !== "") {
				initEditor();
			} else {
				// Se è vuota, carica il default via AJAX
				load_style();
			}
		}


		$('#custom-style-form').on('submit', function () {
			isDirty = false;
		});

		// if css has changed alert user about unsaved changes
		$(window).on('beforeunload', function () {
			if (isDirty) {
				return "You have unsaved changes!";
			}
		});

		// $("#automode-toggler").on("click", function () {
		// 	toggleAutomode();
		// });

		// function toggleAutomode() {
		// 	if ($("#automode-toggler").is(":checked")) {
		// 		$("#menus").show();
		// 		$("#switcher-type").show();
		// 		$("#switcher-classes").show();
		// 	} else {
		// 		$("#menus").hide();
		// 		$("#switcher-type").hide();
		// 		$("#switcher-classes").hide();
		// 	}
		// }

		const $switcherType = $('#switcher_type');
		const $customSelect = $('#custom_list_items');
		const $imageOptions = $customSelect.find('[data-requires-images="1"]');
		function updateOptions() {
			if ($switcherType.val() === "list" || $switcherType.val() === "select") {
				if (!$("#flag_styles").hasClass("hidden")) {
					$("#flag_styles").addClass("hidden");
				}
				$("#dropdown_styles").removeClass("hidden");

				const isSelect = $switcherType.val() === 'select';

				$imageOptions.prop('disabled', isSelect);
				if (isSelect) {
					const $selected = $customSelect.find('option:selected');

					// Se l'opzione selezionata ora è disabilitata
					if ($selected.prop('disabled')) {
						// Seleziona la prima opzione valida
						$customSelect
							.find('option:not(:disabled)')
							.first()
							.prop('selected', true);

						// Notifica eventuali listener
						$customSelect.trigger('change');
					}
				}
			} else {
				$("#flag_styles").removeClass("hidden");
				if (!$("#dropdown_styles").hasClass("hidden")) {
					$("#dropdown_styles").addClass("hidden");
				}
			}
		}
		$(document).on("change", "#switcher_type", updateOptions);

		$(document).on('click', '.shortcode-sel', function (e) {
			copyToClipboard($(this).text(), e);
		});

		function showConfirm(e, message = 'Copied!') {

			// Rimuove eventuali conferme già presenti
			$('.confirm-message').remove();

			const $confirm = $('<div class="confirm-message"></div>')
				.text(message)
				.css({
					position: 'absolute',
					top: e.pageY + 10,
					left: e.pageX + 10,
					padding: '6px 10px',
					background: '#393939',
					color: '#fff',
					borderRadius: '4px',
					fontSize: '13px',
					display: 'none',
					zIndex: 9999
				});

			$('body').append($confirm);

			$confirm
				.fadeIn(200)
				.delay(3000)
				.fadeOut(400, function () {
					$(this).remove();
				});
		}


		function copyToClipboard(element, e) {
			navigator.clipboard.writeText(element).then(
				function () {
					showConfirm(e, 'Copied to clipboard');
				},
				function () {
					alert('Failure to copy. Check permissions for clipboard');
				}
			);
		}


		$("#copy-to-clipboard").on("click", function (e) {
			e.preventDefault();
			copyToClipboard($("#code_editor_page_css").val(), e);
		});

		// toggleAutomode();
		updateOptions();
		load_initial_content();
		$("#select_style").trigger("change");
	}); //end jQuery
})(jQuery);
