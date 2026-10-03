/**
 * Preview notes — short messages shown in the preview note area to set
 * expectations when the live preview can't fully represent a setting.
 *
 * Pure data + selectors, no DOM. Two sources:
 *   - field notes  : the admin edited a setting the preview can't reflect
 *                    ('no-demo') or shows differently from the live site
 *                    ('visibility-differs') — so the preview doesn't look broken.
 *   - state notes  : a consequence of interacting with the preview widget
 *                    (opt-in shows once; badge auto-hides) — 'info'.
 */

/**
 * Field-name prefix → note. The first match (by prefix) wins, so list more
 * specific prefixes first. Editing a matching field surfaces its note.
 *
 * @type {Array<{ match: string, type: string, text: string }>}
 */
export const FIELD_NOTES = [
	{
		match: 'ht_ctc_greetings_settings[g_position',
		type: 'no-demo',
		text: 'Greetings position (e.g. modal dialog) is applied on your live site, not in this preview.',
	},
	{
		match: 'ht_ctc_greetings_settings[g_init',
		type: 'no-demo',
		text: 'Initial stage controls the dialog on first load on your site; the preview keeps it open so you can design it.',
	},
	{
		match: 'ht_ctc_greetings_settings[g_size',
		type: 'no-demo',
		text: 'Greetings dialog size applies on your live site, not in this preview.',
	},
	{
		match: 'ht_ctc_greetings_settings[g_device',
		type: 'no-demo',
		text: 'Greetings device display applies on your live site; the preview always shows the dialog.',
	},
	{
		match: 'ht_ctc_code_blocks[custom_css',
		type: 'no-demo',
		text: 'Custom CSS is applied on your live site, not in this preview.',
	},
	{
		match: 'ht_ctc_othersettings[an_',
		type: 'no-demo',
		text: 'Animations play on your live site — the preview stays static.',
	},
	{
		match: 'ht_ctc_othersettings[show_effect',
		type: 'no-demo',
		text: 'The entry effect plays when the widget appears on your live site, not in this preview.',
	},

	/*
	 * PRO fields. They save into the free option groups, so the matches below
	 * work when PRO renders them — and are inert without PRO (no such fields).
	 */
	{
		match: 'ht_ctc_chat_options[timedelay',
		type: 'no-demo',
		text: 'Show after time delay applies on your live site; the preview is always visible.',
	},
	{
		match: 'ht_ctc_chat_options[scroll',
		type: 'no-demo',
		text: 'Show after scroll applies on your live site; the preview is always visible.',
	},
	{
		match: 'ht_ctc_chat_options[display_countries',
		type: 'no-demo',
		text: 'Country-based display applies on your live site; the preview always shows the widget.',
	},
	{
		match: 'ht_ctc_chat_options[display_user_base',
		type: 'no-demo',
		text: 'User-based display applies on your live site; the preview always shows the widget.',
	},
	{
		match: 'ht_ctc_greetings_settings[g_time_action',
		type: 'no-demo',
		text: 'Open-on-time happens on your live site — click the preview widget to open/close the dialog.',
	},
	{
		match: 'ht_ctc_greetings_settings[g_scroll_action',
		type: 'no-demo',
		text: 'Open-on-scroll happens on your live site — click the preview widget to open/close the dialog.',
	},
	{
		match: 'ht_ctc_greetings_settings[g_no_reopen',
		type: 'no-demo',
		text: 'Reopen behavior applies to visitors on your live site, not in this preview.',
	},
	{
		match: 'ht_ctc_s1[',
		type: 'visibility-differs',
		text: 'Style 1 uses your theme’s button, so the live result can differ from this preview.',
	},
	{
		match: 'ht_ctc_chat_options[display]',
		type: 'visibility-differs',
		text: 'Display rules (incl. WooCommerce pages) apply on your live site; the preview always shows the widget.',
	},
	{
		match: 'ht_ctc_chat_options[mobile_',
		type: 'visibility-differs',
		text: 'Mobile settings apply on real mobile devices — this preview shows the desktop settings.',
	},

	// No entry for style_mobile: picking a mobile style renders it in the
	// floating preview like any style pick (see PreviewManager.currentStyleId).
];

/**
 * The note for a changed field, or null when the field has none.
 *
 * @param {string} name e.g. 'ht_ctc_greetings_settings[g_position]'
 * @returns {{ match: string, type: string, text: string }|null}
 */
export const noteForField = ( name ) => {
	if ( typeof name !== 'string' || name === '' ) { return null; }
	return FIELD_NOTES.find( ( note ) => name.startsWith( note.match ) ) || null;
};

/**
 * Single-line notes shown ONE at a time in the preview note area (the latest
 * interaction wins — they are never concatenated). Each is set on the event it
 * describes, so it's contextual rather than persistent.
 */
export const NOTES = {
	optinOnce: 'On your live site the opt-in shows once per visitor — after they accept, it won’t show again.',
	badgeHidden: 'The notification badge hides once the chat or greeting is opened; change a badge setting to show it again.',
	sameTab: 'No preview link for navigation when Same-tab open (URL Structure).',
	noNumber: 'Add a WhatsApp number to test the chat link.',
};

/**
 * The contextual note for a visibility-state transition, or '' when the change
 * isn't noteworthy (so a stale note clears). Pure — drives PreviewManager's
 * single transientNote.
 *
 * @param {{ badgeStopped: boolean, optinDone: boolean }} prev
 * @param {{ badgeStopped: boolean, optinDone: boolean }} next
 * @returns {string}
 */
export const noteForTransition = ( prev, next ) => {
	if ( ! prev.optinDone && next.optinDone ) { return NOTES.optinOnce; }
	if ( ! prev.badgeStopped && next.badgeStopped ) { return NOTES.badgeHidden; }
	return '';
};
