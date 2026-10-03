jQuery(window).on("elementor:init", function () {
	elementor.on("frontend:init", () => {
		elementorFrontend.on("components:init", () => {
			setVisibilityBorder();
		});
	});
});

jQuery(window).on("elementor:init", function () {
	elementor.hooks.addAction(
		"panel/open_editor/section",
		function (panel, model, view) {
			var cid = model.cid;
			dce_model_cid = cid;
			temporary_disable_visibility(cid);
		},
	);
	elementor.hooks.addAction(
		"panel/open_editor/column",
		function (panel, model, view) {
			var cid = model.cid;
			dce_model_cid = cid;
			temporary_disable_visibility(cid);
		},
	);
	elementor.hooks.addAction(
		"panel/open_editor/widget",
		function (panel, model, view) {
			var cid = model.cid;
			dce_model_cid = cid;
			temporary_disable_visibility(cid);
		},
	);
	elementor.hooks.addAction(
		"panel/open_editor/container",
		function (panel, model, view) {
			var cid = model.cid;
			dce_model_cid = cid;
			temporary_disable_visibility(cid);
		},
	);

	elementor.channels.editor.on("change", (childView, editedElement) => {
		if (childView.model.attributes.name !== "enabled_visibility") {
			return;
		}
		setVisibilityBorder();
	});

	// Add Visibility in Context Menu
	elementor.hooks.addFilter(
		"elements/widget/contextMenuGroups",
		function (groups, element) {
			return dce_add_toggle_visibility(groups, element);
		},
	);

	elementor.hooks.addFilter(
		"elements/section/contextMenuGroups",
		function (groups, element) {
			return dce_add_toggle_visibility(groups, element);
		},
	);
	elementor.hooks.addFilter(
		"elements/column/contextMenuGroups",
		function (groups, element) {
			return dce_add_toggle_visibility(groups, element);
		},
	);
	elementor.hooks.addFilter(
		"elements/container/contextMenuGroups",
		function (groups, element) {
			return dce_add_toggle_visibility(groups, element);
		},
	);

	// Visibility Toggle
	jQuery(document).on(
		"click",
		".dce-elementor-navigator__element__toggle",
		function () {
			var element = jQuery(this).closest(".elementor-navigator__element");
			var cid = element.data("model-cid");
			var eid = jQuery(this).data("eid");
			if (
				jQuery(".elementor-control-enabled_visibility").is(":visible")
			) {
				jQuery(
					".elementor-switch-input[data-setting=enabled_visibility]",
				).click();
			} else {
				dce_visibility_toggle(cid, true);
			}
			return false;
		},
	);
	jQuery(document).on(
		"click",
		".elementor-context-menu-list__item-visibility",
		function () {
			var cid = dce_model_cid;
			dce_visibility_toggle(cid, true);
			return false;
		},
	);
	jQuery(document).on(
		"change",
		".elementor-switch-input[data-setting=enabled_visibility]",
		function () {
			var cid = dce_model_cid;
			dce_visibility_toggle(cid, false);
		},
	);

	// Deselect triggers button event handler
	elementor.channels.editor.on('dceVisibility:deselect_triggers', function(controlView) {
		var element = controlView.container || controlView.getOption('container');
		if (!element) {
			return;
		}
		
		element.model.setSetting('dce_visibility_triggers', []);
		
		// Trigger panel re-render to update the UI
		elementor.getPanelView().getCurrentPageView().render();
	});
});

function dce_add_toggle_visibility(groups, element) {
	groups.push({
		name: "dce_visibility_frontend",
		actions: [
			{
				name: "toggle_visibility",
				title: "Toggle Visibility in Frontend",
				icon: "fa fa-eye",
				callback: function () {
					if (
						element.model.getSetting("enabled_visibility") == "yes"
					) {
						element.model.setSetting("enabled_visibility", "no");
					} else {
						element.model.setSetting("enabled_visibility", "yes");
					}
				},
			},
		],
	});
	return groups;
}

function temporary_disable_visibility(cid) {
	var iFrameDOM = jQuery("iframe#elementor-preview-iframe").contents();
	iFrameDOM
		.find(".dce-visibility-hidden")
		.removeClass("dce-visibility-no-opacity");
	var eid = dce_get_element_id_from_cid(cid);
	iFrameDOM
		.find(".elementor-element[data-id=" + eid + "].dce-visibility-hidden")
		.addClass("dce-visibility-no-opacity");
}

function dce_visibility_is_hidden(cid) {
	if (cid && elementorFrontend.config.elements.data[cid]) {
		var settings = elementorFrontend.config.elements.data[cid].attributes;
		if (settings["enabled_visibility"]) {
			return true;
		}
	}
	return false;
}
function dce_visibility_toggle(cid, change_data) {
	var settings = elementorFrontend.config.elements.data[cid].attributes;
	if (change_data) {
		if (settings["enabled_visibility"]) {
			elementorFrontend.config.elements.data[cid].attributes[
				"enabled_visibility"
			] = "";
		} else {
			elementorFrontend.config.elements.data[cid].attributes[
				"enabled_visibility"
			] = "yes";
			elementorFrontend.config.elements.data[cid].attributes[
				"dce_visibility_hidden"
			] = "yes";
		}
	}

	// color element hidden
	var eid = dce_get_element_id_from_cid(cid);
	var iFrameDOM = jQuery("iframe#elementor-preview-iframe").contents();
	if (settings["enabled_visibility"]) {
		iFrameDOM
			.find(".elementor-element[data-id=" + eid + "]")
			.addClass("dce-visibility-hidden");
	} else {
		iFrameDOM
			.find(".elementor-element[data-id=" + eid + "]")
			.removeClass("dce-visibility-hidden");
	}

	return true;
}

function update_visibility_trigger(cid, eid) {
	if (!eid) {
		var eid = dce_get_element_id_from_cid(cid);
	}
	var iFrameDOM = jQuery("iframe#elementor-preview-iframe").contents();
	iFrameDOM
		.find(
			".elementor-element[data-id=" +
				eid +
				"] > .elementor-dce-visibility",
		)
		.remove();
}

function setVisibilityBorder() {
	var iFrameDOM = jQuery("iframe#elementor-preview-iframe").contents();
	if (window.elementorFrontend) {
		jQuery.each(
			elementorFrontend.config.elements.data,
			function (cid, element) {
				var eid = dce_get_element_id_from_cid(cid);
				if (eid) {
					// check if element is just hidden
					if (dce_visibility_is_hidden(cid)) {
						iFrameDOM
							.find(".elementor-element[data-id=" + eid + "]")
							.addClass("dce-visibility-hidden");
					}
					update_visibility_trigger(cid, eid);
				}
			},
		);
	}
}
