/**
 * Preview Manager
 *
 * Live preview of the chat widget inside the admin SPA.
 *
 * Approach: templates are pure render functions (modules/preview/templates/)
 * lazy-loaded through PreviewRegistry. On every relevant form change the whole
 * widget is re-rendered from the current (unsaved) form values — no DOM
 * mutation maps, so structural changes (different style, conditional elements)
 * are just data.
 *
 * The preview floats at the configured widget position (fixed), mirroring how
 * the widget will look on the site. Toggled from the right sidebar "Preview"
 * tab; the on/off state persists in the shared CtC storage object.
 *
 * PRO / extensions: after registration the App dispatches the
 * `ctc_manager_registered_preview` CustomEvent — use `detail.manager.registry`
 * to register additional style templates.
 */
import { log, debounce, escapeHTML, escapeAttr } from '../core/Utils.js';
import PreviewRegistry from '../preview/PreviewRegistry.js';
import FormValues from '../preview/form-values.js';
import { escapeCssValue } from '../preview/css.js';
import { singleColorIcon, logoIcon, squareIcon } from '../preview/icons.js';
import * as greetingsParts from '../preview/greetings-parts.js';
import { initialState, nextState, eventForField, stateFromStorage, storageFromState } from '../preview/visibility-state.js';
import { noteForField, noteForTransition, NOTES } from '../preview/notes.js';
import { buildWhatsAppUrl } from '../preview/wa-url.js';
import { resolveClickAction } from '../preview/click-action.js';
import { notificationBadgeHtml, applyBadgeOffset } from '../preview/notification-badge.js';
import { uniquifySvgIds } from '../preview/svg-ids.js';

const STORAGE_KEY = 'admin-preview';

// Free styles shipped with template files in modules/preview/templates/.
const FREE_TEMPLATES = [ '1', '2', '3', '3_1', '4', '5', '6', '7', '7_1', '8', '99' ];

// Free greetings dialog templates.
const FREE_GREETINGS = [ 'greetings-1', 'greetings-2' ];

export default class PreviewManager {

	constructor ( app ) {
		this.app = app;
		this.registry = new PreviewRegistry();
		this.form = null;
		this.container = null;
		this.stage = null;
		this.toggle = null;
		this.note = null;
		this.enabled = false;

		// Reveal-on-first-edit: true when the preview starts hidden with no
		// explicit user preference, so the first edit of the session reveals it
		// (once). Set in init() from stored state; cleared by maybeAutoReveal or a
		// manual toggle. See maybeAutoReveal().
		this.autoRevealPending = false;

		// Which style the preview renders: flips to 'mobile' while the admin
		// picks a mobile style and back on a desktop pick (see bindFormEvents).
		// Style only — position always previews the desktop values. Also passed
		// to templates as ctx.device.
		this.device = 'desktop';

		// Guards setNote's switch-to-Preview-tab dispatch: notes that surface
		// during the initial page-load render must not steal the sidebar tab —
		// only notes triggered by the admin's own edits/interactions do.
		this.initialRenderDone = false;

		// Badge / greetings visibility intent — see preview/visibility-state.js.
		// Visibility is DERIVED from this (plus whether greetings is enabled) and
		// reflected as CSS classes on the container, so showing/hiding the badge
		// or CTA never needs a re-render and the badge can't be injected twice.
		this.uiState = initialState();

		// Whether the greetings dialog is actually on screen (intent AND a
		// renderable template). Set by renderGreetings(); feeds the hide classes.
		this.greetingVisible = false;

		// A SINGLE contextual preview note, set by the latest interaction (a
		// no-demo setting edit, a badge/opt-in change, or a navigate result).
		// Shown one at a time — never concatenated. A render-blocking note (no
		// preview for the style/greeting) takes precedence over it in render().
		this.transientNote = '';

		// Opt-in gate (in-memory, per session — NOT persisted; the accepted
		// state IS persisted as optinDone/g_optin). Like the live site, the
		// opt-in stays hidden until a greetings-CTA click reveals it (or the
		// admin is editing an opt-in setting), and only while not yet accepted.
		this.optinGateActive = false;

		// Sticky CTA hard-hide (preview-only). On the live site greetings_open()
		// REMOVES the base-widget call-to-action; the preview can't remove it (the
		// admin may still be editing CTA settings), so instead it adds a class
		// that fully hides the CTA — and keeps it hidden through later open/close
		// (it won't even reveal on hover). Any settings edit clears it, restoring
		// the CTA to its normal behaviour. Set when the greeting opens (init +
		// click-to-open), cleared in bindFormEvents.
		this.ctaHardHidden = false;

		// True while a toast is on screen — the preview shares the bottom corner
		// and stays hidden so it never pops over the toast (see bindToastEvents).
		this.toastVisible = false;
		this.renderDebounced = debounce( () => this.render(), 120 );

		// Monotonic counter to make SVG ids unique across swapped grid cells.
		this.gridUidCounter = 0;

		// Number providers for click-to-navigate. Extensions (e.g. PRO Random
		// Numbers) push `(manager) => number|''` functions; the first non-empty
		// result wins over the WhatsApp Number field — mirroring the frontend,
		// where ht_ctc_event_number lets PRO override ctc.number. Neutral hook
		// only: the free plugin knows nothing about PRO option shapes.
		this.numberProviders = [];

		// Pre-filled prefix providers. Extensions (e.g. PRO greetings form) push
		// `(manager) => string|''` functions; the first non-empty result becomes
		// the pre-filled prefix — mirroring the frontend's ctc.prefix_pre_filled
		// (which the free app.js already prepends). Lets the form inject typed
		// field values into the message without the free plugin knowing the form.
		this.preFilledPrefixProviders = [];
	}

	/**
	 * The number used when the preview navigates to WhatsApp: the first
	 * non-empty provider result, else the WhatsApp Number field.
	 *
	 * @returns {string}
	 */
	resolveNumber () {
		for ( const provider of this.numberProviders ) {
			const num = String( provider( this ) || '' )
				.trim();
			if ( num !== '' ) { return num; }
		}
		return this.values.get( 'ht_ctc_chat_options', 'number' ) || '';
	}

	/**
	 * The pre-filled prefix (frontend ctc.prefix_pre_filled): the first
	 * non-empty provider result, else ''. Providers already fold in the global
	 * pre-filled setting, so a non-empty result IS the full message base.
	 *
	 * @returns {string}
	 */
	resolvePreFilledPrefix () {
		for ( const provider of this.preFilledPrefixProviders ) {
			const prefix = String( provider( this ) || '' );
			if ( prefix !== '' ) { return prefix; }
		}
		return '';
	}

	/**
	 * Substitute the message variables the admin can actually resolve. The
	 * frontend replaces {site}/{url}/{title} server-side per page; in the admin
	 * only {site} (the blog name) is known, so {url}/{title} stay literal —
	 * consistent with the greeting-content helper (greetings-parts.js) and the
	 * note the admin sees. Product variables need page context and are left too.
	 *
	 * @param {string} text
	 * @returns {string}
	 */
	applyMessageVariables ( text ) {
		const site = this.app.config?.preview?.site || '';
		if ( site === '' || typeof text !== 'string' ) { return text; }
		return text.replaceAll( '{{site}}', site )
			.replaceAll( '{site}', site );
	}

	init () {
		this.form = document.getElementById( 'ctc-settings-form' );
		if ( ! this.form ) { return; }

		// Reads option values from the form (unsaved edits win) with saved
		// settings as fallback. See preview/form-values.js.
		this.values = new FormValues( this.form, this.app.config?.initialSettings );

		// Sync initial toast visibility state in case a toast was already active on load.
		// this.toastVisible = ! ! document.getElementById( 'toast' )?.classList.contains( 'show' );

		this.registerFreeTemplates();
		this.buildContainer();
		this.injectFrontCss();
		this.bindToggle();
		this.bindFormEvents();
		this.bindToastEvents();

		// Re-render on resize: the fit-to-bounds scale depends on the viewport,
		// so a resized window needs a fresh measure/scale pass.
		window.addEventListener( 'resize', () => {
			if ( this.enabled ) { this.renderDebounced(); }
		} );

		// Restore persisted visibility from the shared ht_ctc_storage so a closed
		// dialog stays closed, a dismissed badge stays dismissed, and a completed
		// opt-in stays gone across reloads (paired with persistState()). Seeded
		// before the first render so it takes effect immediately.
		this.uiState = stateFromStorage( ( key ) => this.app.storage.getCtcStorageItem( key ) );

		// If the greeting is already open on load (and actually configured), the
		// CTA starts hard-hidden — mirrors the live widget where the auto-opened
		// dialog removes the CTA. Stays hidden until the admin edits a setting.
		this.ctaHardHidden = this.uiState.greetingOpen && this.greetingEnabled();

		// Default hidden on first run: with no explicit toggle choice the floating
		// preview stays hidden until the first edit reveals it (maybeAutoReveal).
		// An explicit choice ('on'/'off') is always honored and disables the
		// auto-reveal, so a pinned-on or pinned-off preview keeps that state.
		const storedPref = this.app.storage.getCtcStorageItem( STORAGE_KEY );
		const hasExplicitPref = storedPref === 'on' || storedPref === 'off';
		this.enabled = storedPref === 'on';
		this.autoRevealPending = ! hasExplicitPref;
		if ( this.toggle ) { this.toggle.checked = this.enabled; }
		if ( this.enabled ) {
			// Notes set during this first render (e.g. a style/greeting caveat
			// for the saved settings) must not steal the sidebar tab on load —
			// arm setNote's tab-switch only after the initial render settles.
			this.render()
				.finally( () => { this.initialRenderDone = true; } );
		} else {
			this.initialRenderDone = true;
		}

		// Replace the CSS placeholders in the "Select Style" grids with the real
		// widget previews now that the templates are available. Independent of
		// the floating preview's on/off state.
		this.enhanceStyleGrids();
	}

	/**
	 * Inject the front-end CSS into the document head once so greetings preview
	 * templates can rely on front-end classes (badge positioning, image wrapper, etc.)
	 * without duplicating styles inline.
	 */
	injectFrontCss () {
		const url = this.app.config?.paths?.front_css;
		if ( ! url || document.querySelector( 'link[data-ctc-front-css]' ) ) { return; }
		const link = document.createElement( 'link' );
		link.rel = 'stylesheet';
		link.href = url;
		link.setAttribute( 'data-ctc-front-css', '1' );
		document.head.appendChild( link );
	}

	/**
	 * Register lazy loaders for the free style templates.
	 * Template modules live next to the admin bundle, dev or min path is
	 * resolved by PHP into config.preview.templatesBasePath.
	 */
	registerFreeTemplates () {
		const base = this.app.config?.preview?.templatesBasePath;
		if ( ! base ) {
			log( 'Preview', 'No templatesBasePath in config — preview templates unavailable.' );
			return;
		}

		FREE_TEMPLATES.forEach( ( id ) => {
			const url = `${base}style-${id}.js`;
			this.registry.registerStyle( id, () =>
				// eslint-disable-next-line no-unsanitized/method -- URL is built from trusted plugin configuration localized by PHP
				import( /* webpackIgnore: true */ url ) );
		} );

		FREE_GREETINGS.forEach( ( id ) => {
			const url = `${base}${id}.js`;
			this.registry.registerGreeting( id, () =>
				// eslint-disable-next-line no-unsanitized/method -- URL is built from trusted plugin configuration localized by PHP
				import( /* webpackIgnore: true */ url ) );
		} );
	}

	/**
	 * Floating fixed container appended to body.
	 * `ht-ctc` class scopes the per-style <style> blocks (hover rules) the same
	 * way the frontend wrapper does.
	 */
	buildContainer () {
		this.container = document.createElement( 'div' );
		this.container.id = 'ht-ctc-admin-preview';
		this.container.className = 'ht-ctc';
		this.container.style.cssText = 'position:fixed;display:none;z-index:99999;cursor:pointer;';
		this.container.title = 'Click to Chat — preview';

		// Master state rules: badge/CTA visibility is driven by classes on the
		// container (toggled in syncStateClasses) rather than by mutating the
		// rendered markup — so a re-render is never needed just to hide them, and
		// the badge can't be injected twice. The #id prefix raises specificity
		// above the templates' :hover reveal rule.
		// Opt-in is hidden until the gate reveals it (greetings-CTA click /
		// editing an opt-in setting); mirrors the live site.
		const stateCss = document.createElement( 'style' );
		stateCss.textContent =
			'#ht-ctc-admin-preview.ctc-state-badge-hidden .ht_ctc_notification{display:none !important;}' +
			'#ht-ctc-admin-preview.ctc-state-greeting-open .ctc_cta_stick,' +
			'#ht-ctc-admin-preview.ctc-state-greeting-open .ht-ctc-cta-hover{display:none !important;}' +

			// Sticky CTA hard-hide (set once the greeting opens; cleared on a
			// settings edit). Fully hides the CTA and overrides the templates'
			// :hover reveal, so it stays gone through later open/close — not just
			// while the dialog is open. The #id prefix raises specificity above
			// the `.ctc_s_*:hover .ht-ctc-cta-hover` reveal rule.
			'#ht-ctc-admin-preview.ctc-cta-hidden .ctc_cta_stick,' +
			'#ht-ctc-admin-preview.ctc-cta-hidden .ht-ctc-cta-hover{display:none !important;}' +
			'#ht-ctc-admin-preview .ctc_opt_in{display:none !important;}' +
			'#ht-ctc-admin-preview.ctc-state-optin-show .ctc_opt_in{display:block !important;}';
		this.container.appendChild( stateCss );

		// Greetings dialog box sits above the widget (same wrapper structure
		// as the frontend: ht_ctc_chat_greetings_box > _layout > template).
		this.greetingsBox = document.createElement( 'div' );
		this.greetingsBox.className = 'ht_ctc_chat_greetings_box';
		this.greetingsBox.style.cssText = 'display:none;position:absolute;bottom:calc(100% + 12px);max-width:420px;cursor:auto;';
		this.container.appendChild( this.greetingsBox );

		this.stage = document.createElement( 'div' );
		this.stage.className = 'ht_ctc_style ht_ctc_chat_style';
		this.container.appendChild( this.stage );

		// Routing is decided by the pure resolveClickAction(); this handler only
		// does the DOM matching and runs the resulting action (see runClickAction).
		this.container.addEventListener( 'click', ( event ) => {
			// Chat triggers with their own number — the same generic
			// .ctc_chat[data-number] contract the frontend's ht_ctc_link honors
			// (extensions render such elements, e.g. inside the dialog).
			const numberedChat = event.target.closest( '.ctc_chat[data-number]' );
			const action = resolveClickAction( {
				optin: Boolean( event.target.closest( '.ctc_opt_in' ) ),
				closeBtn: Boolean( event.target.closest( '.ctc_greetings_close_btn' ) ),
				greetingCta: Boolean( event.target.closest( '.ht_ctc_chat_greetings_box_link' ) ),
				numberedChat: Boolean( numberedChat ),
				baseWidget: Boolean( event.target.closest( '.ht_ctc_chat_style' ) ),
			}, {
				greetingEnabled: this.greetingEnabled(),
				optinEnabled: this.optinEnabled(),
				optinDone: this.uiState.optinDone,
			} );
			this.runClickAction( action, {
				number: numberedChat?.dataset.number || '',

				// Only a PRESENT data-pre_filled overrides the global message
				// (mirrors ht_ctc_link's hasAttribute check — '' is a valid
				// override meaning "no message").
				preFilled: numberedChat?.hasAttribute( 'data-pre_filled' ) ?
					numberedChat.dataset.pre_filled || '' :
					undefined,
			} );
		} );

		document.body.appendChild( this.container );
	}

	/**
	 * Run an action from resolveClickAction() — the side-effecting half of the
	 * click router (state transitions / navigation / the opt-in gate reveal).
	 *
	 * @param {string|null} action
	 * @param {{ number?: string, preFilled?: string }} [payload] Extra click
	 *   context for 'numbered_navigate' — the clicked element's data-number and
	 *   (when the attribute is present) its data-pre_filled override.
	 */
	runClickAction ( action, payload = {} ) {
		switch ( action ) {
			case 'optin_accept':
				this.applyEvent( 'optin_click' );
				break;
			case 'greeting_close':
				this.applyEvent( 'greeting_close' );
				break;
			case 'optin_reveal':
				// First CTA click reveals the opt-in (gate); no navigation yet.
				this.optinGateActive = true;
				this.syncStateClasses();
				break;
			case 'navigate':
				this.openWhatsApp();
				break;
			case 'numbered_navigate':
				this.openWhatsApp( payload.number, payload.preFilled );
				break;
			case 'greeting_toggle':
				this.applyEvent( 'greeting_toggle' );

				// Opening the dialog hard-hides the CTA (mirrors live greetings_open
				// removing it); it then stays hidden through later close/open until a
				// settings edit. A toggle that CLOSES leaves the flag untouched.
				if ( this.uiState.greetingOpen ) {
					this.ctaHardHidden = true;
					this.syncStateClasses();
				}
				break;
			case 'base_navigate':
				this.applyEvent( 'widget_click' );
				this.openWhatsApp();
				break;
			default:
				break;
		}
	}

	/**
	 * Advance the visibility state machine for an event and reflect it.
	 *
	 * Badge/CTA visibility is pure CSS (syncStateClasses), so it updates
	 * instantly; the dialog is (re)built by render(). Closing hides the dialog
	 * immediately so it doesn't linger through the render debounce.
	 *
	 * @param {string} event widget_click | greeting_toggle | greeting_close |
	 *   notification_change | greeting_change | optin_change | optin_click
	 */
	applyEvent ( event ) {
		const prev = this.uiState;
		this.uiState = nextState( prev, event );
		this.persistState();

		// Opt-in gate: only an opt-in-setting edit (re)shows the opt-in via an
		// event; every other transition clears the gate. The greetings-CTA
		// click sets it directly (outside applyEvent).
		this.optinGateActive = ( event === 'optin_change' );

		// One contextual note for what THIS interaction changed (replaces any
		// previous note; '' clears it — so a stale navigate note can't linger
		// onto a greeting toggle).
		this.transientNote = noteForTransition( prev, this.uiState );

		// Closing: hide the dialog now; render() would only catch up after the debounce.
		if ( prev.greetingOpen && ! this.uiState.greetingOpen ) {
			this.greetingsBox.style.display = 'none';
			this.greetingVisible = false;
		}

		this.syncStateClasses();
		if ( this.enabled ) { this.renderDebounced(); }
	}

	/**
	 * Persist the current badge/dialog state to the shared ht_ctc_storage keys
	 * (n_badge / g_user_action) so it survives a reload and stays consistent
	 * with the live widget on the same browser.
	 */
	persistState () {
		storageFromState( this.uiState )
			.forEach( ( [ key, value ] ) => this.app.storage.setCtcStorageItem( key, value ) );
	}

	/**
	 * Whether a greetings dialog is configured (template set, not 'no'). When
	 * true, click-to-navigate lives on the dialog's CTA, not the base widget.
	 *
	 * @returns {boolean}
	 */
	greetingEnabled () {
		const templateId = String( this.values.get( 'ht_ctc_greetings_options', 'greetings_template' ) || '' );
		return templateId !== '' && templateId !== 'no';
	}

	/**
	 * Whether the opt-in is enabled (is_opt_in set). When on, the greetings CTA
	 * reveals the opt-in before navigating (the opt-in gate).
	 *
	 * @returns {boolean}
	 */
	optinEnabled () {
		return ( this.values.get( 'ht_ctc_greetings_settings', 'is_opt_in' ) || '' ) !== '';
	}

	/**
	 * Open WhatsApp like the frontend link: URL from the desktop URL-structure
	 * setting (wa.me / web.whatsapp / custom URL) with the number + pre-filled
	 * message. Mobile is ignored for now. Two cases show a note instead of
	 * navigating: same-tab (url_target_d = _self) can't be demoed here, and a
	 * missing number (with no custom URL) prompts the admin to add one. The
	 * note surfaces through render(), so refresh it.
	 *
	 * @param {string} [overrideNumber] Number that wins over providers/settings
	 *   (a clicked element's own data-number).
	 * @param {string} [overridePreFilled] Message that replaces the pre-filled
	 *   setting (a clicked element's own data-pre_filled; '' is a valid
	 *   override, so only undefined falls back). The pre-filled prefix is still
	 *   prepended, mirroring the frontend's `prefix_pre_filled + data-pre_filled`.
	 */
	openWhatsApp ( overrideNumber = '', overridePreFilled = undefined ) {
		const target = this.values.get( 'ht_ctc_chat_options', 'url_target_d' ) || '_blank';
		if ( target === '_self' ) {
			this.transientNote = NOTES.sameTab;
		} else {
			// Mirror ht_ctc_link: an element with its own data-pre_filled gets
			// prefix + own message; otherwise the prefix (which already folds in
			// the global pre-filled) is the message, else the raw global setting.
			const prefix = this.resolvePreFilledPrefix();
			const preFilled = overridePreFilled !== undefined ?
				prefix + overridePreFilled :
				( prefix !== '' ? prefix : this.values.get( 'ht_ctc_chat_options', 'pre_filled' ) );
			const url = buildWhatsAppUrl( {
				number: overrideNumber || this.resolveNumber(),
				preFilled: this.applyMessageVariables( preFilled ),
				urlStructure: this.values.get( 'ht_ctc_chat_options', 'url_structure_d' ),
				customUrl: this.values.get( 'ht_ctc_chat_options', 'custom_url_d' ),
			} );
			if ( url ) {
				this.transientNote = '';

				// Mirror the frontend: a 'popup' target opens a sized popup
				// window; any other target (_blank) opens a new tab.
				const features = ( target === 'popup' ) ?
					'scrollbars=no,resizable=no,status=no,location=no,' +
						'toolbar=no,menubar=no,width=788,height=514,left=100,top=100' :
					'noopener';
				window.open( url, target, features );
			} else {
				this.transientNote = NOTES.noNumber;
			}
		}
		if ( this.enabled ) { this.renderDebounced(); }
	}

	/**
	 * Reflect the derived badge/CTA visibility on the container via CSS state
	 * classes. Idempotent — safe to call on every render.
	 */
	syncStateClasses () {
		if ( ! this.container ) { return; }
		const badgeHidden = this.uiState.badgeStopped || this.greetingVisible;
		this.container.classList.toggle( 'ctc-state-badge-hidden', badgeHidden );
		this.container.classList.toggle( 'ctc-state-greeting-open', this.greetingVisible );
		this.container.classList.toggle( 'ctc-cta-hidden', this.ctaHardHidden );
		this.container.classList.toggle( 'ctc-state-optin-show', this.optinGateActive && ! this.uiState.optinDone );
	}

	bindToggle () {
		this.toggle = document.getElementById( 'ctc-preview-toggle' );
		this.note = document.getElementById( 'ctc-preview-note' );

		if ( ! this.toggle ) { return; }

		this.toggle.addEventListener( 'change', () => {
			this.enabled = this.toggle.checked;

			// A manual toggle IS the explicit preference — persist it and stop the
			// first-edit auto-reveal from overriding an off choice made pre-edit.
			this.autoRevealPending = false;
			this.app.storage.setCtcStorageItem( STORAGE_KEY, this.enabled ? 'on' : 'off' );
			if ( this.enabled ) {
				this.render();
			} else {
				this.container.style.display = 'none';
			}
		} );

	}

	/**
	 * First-edit reveal (once per session).
	 *
	 * When the preview started hidden with no explicit user preference, the first
	 * meaningful edit reveals the floating preview and focuses the sidebar's
	 * Preview tab — so the admin sees the effect of the change they just made. A
	 * manual toggle choice clears autoRevealPending, so this never fights a
	 * pinned-on/off preview. The reveal is session-only and NOT persisted: each
	 * load starts hidden until the first edit, unless the toggle is pinned.
	 *
	 * enabled is flipped here; the caller (onFieldChange) then renders through its
	 * normal path now that the preview is on.
	 */
	maybeAutoReveal () {
		if ( ! this.autoRevealPending ) { return; }
		this.autoRevealPending = false;
		this.enabled = true;
		if ( this.toggle ) { this.toggle.checked = true; }

		// Same channel setNote uses to surface the preview; activateTab no-ops if
		// Preview is already the active sidebar tab.
		document.dispatchEvent( new CustomEvent( 'ctc_open_sidebar_tab', {
			detail: { tab: 'preview' },
		} ) );
	}

	bindFormEvents () {
		const onFieldChange = ( name ) => {
			// First meaningful edit reveals a first-run-hidden preview (once).
			this.maybeAutoReveal();

			const fieldNote = noteForField( name );

			// Style selection drives which style the preview renders: picking a
			// mobile style previews it (just like a desktop pick), and the next
			// desktop-style pick switches back. Only style fields flip this —
			// position and everything else always previews the desktop values.
			if ( name.includes( 'style_mobile' ) ) {
				this.device = 'mobile';
			} else if ( name.includes( 'style_desktop' ) ) {
				this.device = 'desktop';
			}

			// Any settings edit restores the CTA to normal: clear the sticky
			// hard-hide that an opened greeting set. Cleared before the re-render
			// below (applyEvent / renderDebounced both call syncStateClasses), and
			// not re-set by render(), so even an edit that reopens the greeting
			// leaves the CTA restored.
			this.ctaHardHidden = false;

			// Editing a badge or greetings setting resets that element's
			// visibility (applyEvent) so the admin sees the change they made.
			const stateEvent = eventForField( name );
			if ( stateEvent ) {
				this.applyEvent( stateEvent );

				// A no-demo field (e.g. g_position) note wins over the
				// transition's note for that edit; applyEvent already re-renders.
				if ( fieldNote ) { this.transientNote = fieldNote.text; }
				return;
			}

			// Other edits: show this field's note, or clear a stale one.
			this.transientNote = fieldNote ? fieldNote.text : '';
			if ( this.enabled ) { this.renderDebounced(); }
		};

		const onFormEvent = ( event ) => {
			const name = event?.target?.name || '';
			if ( ! name.startsWith( 'ht_ctc_' ) ) { return; }
			onFieldChange( name );
		};

		this.form.addEventListener( 'input', onFormEvent );
		this.form.addEventListener( 'change', onFormEvent );

		// Programmatic changes (intl number input, repeaters, …) flow through the bus.
		this.app.events?.on( 'field:dirty', ( payload ) => {
			onFieldChange( payload?.name || payload?.target?.name || '' );
		} );
	}

	/**
	 * Hide the floating preview while a toast is visible (they share the
	 * bottom-corner space) and bring it back once the toast clears. The
	 * re-show goes through render(), so it only reappears if the preview is
	 * still enabled and renders cleanly — a failed render stays hidden.
	 */
	bindToastEvents () {
		const events = this.app.events;
		if ( ! events ) { return; }

		events.on( 'toast:show', () => {
			this.toastVisible = true;
			if ( this.enabled && this.container ) {
				this.container.style.display = 'none';
			}
		} );

		events.on( 'toast:hidden', () => {
			this.toastVisible = false;
			if ( this.enabled ) { this.render(); }
		} );
	}

	/**
	 * Validate a CSS length from the position fields. Falls back rather than
	 * injecting arbitrary strings into inline styles.
	 *
	 * @param {string} value
	 * @param {string} fallback
	 * @returns {string}
	 */
	cssLength ( value, fallback = '15px' ) {
		const length = String( value ?? '' )
			.trim();
		const isValid = ( /^-?[\d.]{1,10}(px|%|em|rem|vh|vw)?$/ ).test( length );
		return isValid ? length : fallback;
	}

	/**
	 * Apply the configured desktop position (side_1/side_2) to the container.
	 *
	 * The preview is desktop-only: mobile-specific settings (style_mobile,
	 * mobile_side_*) are not previewed — they apply on real mobile devices
	 * (see the FIELD_NOTES entries in preview/notes.js).
	 */
	applyPosition () {
		const side1 = this.values.get( 'ht_ctc_chat_options', 'side_1' ) === 'top' ? 'top' : 'bottom';
		const side2 = this.values.get( 'ht_ctc_chat_options', 'side_2' ) === 'left' ? 'left' : 'right';
		const side1Value = this.cssLength( this.values.get( 'ht_ctc_chat_options', 'side_1_value' ) );
		const side2Value = this.cssLength( this.values.get( 'ht_ctc_chat_options', 'side_2_value' ) );

		const style = this.container.style;
		style.top = '';
		style.bottom = '';
		style.left = '';
		style.right = '';

		// side1/side2 are constrained above to top|bottom / left|right.
		style.setProperty( side1, side1Value );
		style.setProperty( side2, side2Value );

		// Anchor any fit-to-bounds scaling (see fitToBounds) to the pinned
		// corner, and drop a previous render's scale so the next measure is of
		// the natural, unscaled size.
		style.transformOrigin = `${side1} ${side2}`;
		style.transform = '';

		return side2;
	}

	setNote ( message ) {
		if ( ! this.note ) { return; }

		const text = message || '';
		const changed = this.note.textContent !== text;
		this.note.textContent = text;

		// A newly surfaced note is feedback the admin should see — bring the
		// right sidebar's Preview tab forward (Interface.initRightSidebar
		// listens; no-op when it's already showing). Only on a CHANGED note:
		// re-renders re-set the same note constantly, and re-dispatching would
		// keep forcing the tab open after the admin deliberately closed it.
		// Suppressed during the initial page-load render (initialRenderDone) so
		// a pre-existing caveat doesn't steal the tab before any interaction.
		if ( text && changed && this.initialRenderDone ) {
			document.dispatchEvent( new CustomEvent( 'ctc_open_sidebar_tab', {
				detail: { tab: 'preview' },
			} ) );
		}
	}

	/**
	 * Resolve which style id to preview: the mobile style while the admin is
	 * picking one (device flips on style edits — see bindFormEvents),
	 * otherwise the desktop style.
	 *
	 * @returns {string}
	 */
	currentStyleId () {
		const desktop = String( this.values.get( 'ht_ctc_chat_options', 'style_desktop' ) || '4' );
		if ( 'mobile' === this.device ) {
			return String( this.values.get( 'ht_ctc_chat_options', 'style_mobile' ) || desktop );
		}
		return desktop;
	}

	async render () {
		if ( ! this.container ) { return; }

		const styleId = this.currentStyleId();
		const renderFn = await this.registry.getStyleRenderer( styleId );

		// Guard against race: a slower import finishing after the user toggled off.
		if ( ! this.enabled ) { return; }

		const side2 = this.applyPosition();

		const ctx = this.buildContext( side2 );

		const notes = [];

		if ( ! renderFn ) {

			const fallbackStyle = 'background:#fff;border:1px solid #dcdcde;' +
				'border-radius:6px;padding:8px 12px;font-size:12px;color:#50575e;' +
				'box-shadow:0 1px 4px rgba(0,0,0,.12);';
			// eslint-disable-next-line no-unsanitized/property -- Static markup; style id is escaped
			this.stage.innerHTML = `<div style="${fallbackStyle}">` +
				`${escapeHTML( `No live preview yet for Style ${styleId}` )}</div>`;
			notes.push( `Live preview is not yet available for Style ${styleId}.` );
		} else {
			try {
				// Templates return an html string, or { html, note } when the
				// preview needs a caveat (e.g. style 1 depends on the theme).
				const rendered = renderFn( ctx );
				const html = ( typeof rendered === 'string' ) ? rendered : rendered.html;
				const note = ( typeof rendered === 'string' ) ? '' : ( rendered.note || '' );

				// eslint-disable-next-line no-unsanitized/property -- Templates escape all dynamic values (escapeHTML/escapeAttr/escapeCssValue)
				this.stage.innerHTML = notificationBadgeHtml( this.values ) + html;
				applyBadgeOffset( this.stage );
				if ( note ) { notes.push( note ); }
			} catch ( error ) {
				log( 'Preview', `Render failed for style ${styleId}`, error );
				this.container.style.display = 'none';
				return;
			}
		}

		const greetingsNote = await this.renderGreetings( ctx, side2 );
		if ( greetingsNote ) { notes.push( greetingsNote ); }

		// Mirror the frontend: an open greetings dialog dismisses the badge for
		// good (greetings_open() calls stop_notification_badge()), so it won't
		// reappear when the dialog is closed — only when a badge setting changes.
		if ( this.greetingVisible ) { this.uiState.badgeStopped = true; }

		// renderGreetings() has set greetingVisible — reflect badge/CTA hide
		// classes (covers styles re-rendered while the dialog is open).
		this.syncStateClasses();

		// Show ONE note: a render-blocking note (no preview for this style /
		// greeting) wins; otherwise the latest interaction's contextual note.
		const renderNote = notes.join( ' ' );
		this.setNote( renderNote || this.transientNote );

		// Don't pop back over a visible toast — they share the bottom corner.
		// The toast:hidden handler re-renders once it clears.
		this.container.style.display = this.toastVisible ? 'none' : 'block';

		// Preview-only safety: an oversized setting (e.g. a 5000px Style 2 image
		// size) must not let the preview take over the admin screen. Scale the
		// rendered widget down to fit a sane box. This is purely visual — the
		// saved value is left exactly as the user typed it.
		if ( this.container.style.display === 'block' ) {
			this.fitToBounds();
		}

		// Generic post-render hook for extensions (e.g. PRO's date scheduler,
		// which re-inits its calendar modal on the freshly rendered stage). The
		// stage's innerHTML was just replaced, so listeners bound to its children
		// are gone — consumers should re-bind here. Mirrors the document-event
		// convention used for `ctc_manager_registered_*`.
		document.dispatchEvent( new CustomEvent( 'ctc_preview_rendered', {
			detail: { stage: this.stage, container: this.container },
		} ) );
	}

	/**
	 * Keep the floating preview from overflowing the admin screen.
	 *
	 * Real widgets are small (~50–70px), but Image Size / dimension fields are
	 * free text, so a typo like `5000px` can make the rendered widget cover the
	 * whole viewport and feel impossible to recover from. This scales the
	 * container down toward its pinned corner (transform-origin set in
	 * applyPosition) so it never exceeds a safe box. Style-agnostic: it caps any
	 * style and any oversized dimension. Purely a preview cap — the saved value
	 * is never touched, so the live widget renders whatever the user chose.
	 */
	fitToBounds () {
		const el = this.container;
		if ( ! el ) { return; }

		// Measure the natural size (applyPosition already cleared any prior
		// transform for this render).
		const naturalWidth = el.offsetWidth;
		const naturalHeight = el.offsetHeight;
		if ( ! naturalWidth || ! naturalHeight ) { return; }

		// Generous enough that normal widgets never scale; small enough that a
		// runaway value can't dominate the screen.
		const maxWidth = Math.min( window.innerWidth * 0.45, 360 );
		const maxHeight = Math.min( window.innerHeight * 0.55, 360 );

		const scale = Math.min( 1, maxWidth / naturalWidth, maxHeight / naturalHeight );
		if ( scale < 1 ) {
			el.style.transform = `scale(${scale})`;
		}
	}

	/**
	 * Build the render context shared by every style/greetings template.
	 *
	 * Templates read settings only through `value`/`groupValue` (per option
	 * group), so the same context renders any style correctly — the floating
	 * preview passes the selected style, the style grid reuses it per cell.
	 *
	 * @param {string} side2 'left' | 'right' — which side the widget hugs.
	 * @returns {Object} Template render context.
	 */
	buildContext ( side2 ) {
		return {
			cta: String( this.values.get( 'ht_ctc_chat_options', 'call_to_action' ) ?? '' ),
			side2,
			isRtl: document.documentElement.dir === 'rtl',
			device: this.device,
			site: this.app.config?.preview?.site || '',

			// WP timezone offset (hours) for business-hours-aware previews (PRO
			// multi-agent). Mirrors the front end's ctc.tz = gmt_offset.
			wpTzOffset: Number( this.app.config?.preview?.wpTzOffset ) || 0,
			pluginUrl: this.app.config?.paths?.plugin_url || '',
			proPluginUrl: this.app.config?.paths?.pro_plugin_url || '',
			value: ( group, key ) => this.values.get( group, key ),
			groupValue: ( group ) => this.values.groupValues( group ),

			// Shared helpers so external templates (PRO) need no cross-plugin imports.
			esc: { attr: escapeAttr, html: escapeHTML, css: escapeCssValue },
			parts: { ...greetingsParts, singleColorIcon, logoIcon, squareIcon },
		};
	}

	/**
	 * Swap the CSS placeholder in each "Select Style" grid cell for the real
	 * widget, rendered from the same templates the floating preview uses.
	 *
	 * Runs once when the preview module loads. The grid is a static picker, so
	 * cells are NOT re-rendered on later edits — the floating preview is the live
	 * one. Each cell previews its own style id (from `data-style-id`) with that
	 * style's saved settings; cells whose style has no registered template keep
	 * their CSS placeholder, so this degrades gracefully (e.g. PRO styles before
	 * PRO registers, or if templates fail to load).
	 *
	 * @returns {Promise<void>}
	 */
	async enhanceStyleGrids () {
		const cells = document.querySelectorAll( '.grid-widget-preview[data-style-id]' );
		if ( ! cells.length ) { return; }

		// Neutral 'right' side keeps CTA ordering consistent without touching the
		// floating preview's configured position.
		const ctx = this.buildContext( 'right' );

		await Promise.all( Array.from( cells )
			.map( ( cell ) => this.renderStyleGridCell( cell, ctx ) ) );
	}

	/**
	 * Render one style template into a single grid cell.
	 *
	 * @param {HTMLElement} cell The `.grid-widget-preview` element.
	 * @param {Object}      ctx  Shared template render context.
	 * @returns {Promise<void>}
	 */
	async renderStyleGridCell ( cell, ctx ) {
		const styleId = cell.getAttribute( 'data-style-id' );
		if ( ! styleId || ! this.registry.hasStyle( styleId ) ) { return; }

		const renderFn = await this.registry.getStyleRenderer( styleId );
		if ( typeof renderFn !== 'function' ) { return; }

		let html;
		try {
			const rendered = renderFn( ctx );
			html = ( typeof rendered === 'string' ) ? rendered : rendered.html;
		} catch ( error ) {
			log( 'Preview', `Grid cell render failed for style ${styleId}`, error );
			return;
		}

		const stage = document.createElement( 'div' );
		stage.className = 'ht_ctc_style ht_ctc_chat_style';
		// eslint-disable-next-line no-unsanitized/property -- Templates escape all dynamic values (escapeHTML/escapeAttr/escapeCssValue)
		stage.innerHTML = html;

		// Keep the templates' <style> blocks (some, e.g. Style 5, set the resting
		// state there), but drop their ids so the same style across cells/grids
		// doesn't create duplicate element ids. Their rules are class-scoped and
		// hover-only rules never fire here (cells are pointer-events:none).
		stage.querySelectorAll( 'style[id]' )
			.forEach( ( node ) => node.removeAttribute( 'id' ) );

		// The icon SVGs use fixed ids (e.g. gradient "htwaicona-chat"). The same
		// icon rendered in several cells (and the floating preview) would then
		// share ids, so `url(#id)` fills resolve to the wrong element and render
		// blank in some engines. Localise every id to this cell.
		uniquifySvgIds( stage, `-cg${++this.gridUidCounter}` );

		cell.replaceChildren( stage );
		cell.classList.add( 'has-live-preview' );
	}

	/**
	 * Render the greetings dialog above the widget when a greetings template
	 * is selected. Returns an optional note for the sidebar.
	 *
	 * @param {Object} ctx   Render context shared with the style template.
	 * @param {string} side2 'left' | 'right' — which side the widget hugs.
	 * @returns {Promise<string>} Note text ('' when none).
	 */
	async renderGreetings ( ctx, side2 ) {
		const templateId = String( this.values.get( 'ht_ctc_greetings_options', 'greetings_template' ) || '' );

		// '' and 'no' both mean greetings is disabled.
		if ( templateId === '' || templateId === 'no' || ! this.uiState.greetingOpen ) {
			this.greetingsBox.style.display = 'none';
			this.greetingVisible = false;
			return '';
		}

		const renderFn = await this.registry.getGreetingRenderer( templateId );
		if ( ! this.enabled ) { return ''; }

		if ( ! renderFn ) {
			this.greetingsBox.style.display = 'none';
			this.greetingVisible = false;
			return `Live preview is not yet available for the "${templateId}" greetings template.`;
		}

		// Box sizing by greetings size setting (same as the 2019 admin demo).
		const gSize = this.values.get( 'ht_ctc_greetings_settings', 'g_size' ) || 's';
		let minWidth = '300px';
		if ( gSize === 'm' ) { minWidth = '330px'; } else if ( gSize === 'l' ) { minWidth = '360px'; }

		const closeSide = ctx.isRtl ? 'left' : 'right';

		try {
			const html = renderFn( ctx );

			const boxLayoutStyles = 'max-height:84vh;overflow-y:auto;' +
				'box-shadow:0px 1px 9px 0px rgba(0,0,0,.14);border-radius:8px;clear:both;';

			const closeBtnStyles = 'position:absolute;top:0;' +
				`${closeSide}:0;cursor:pointer;padding:5px;margin:4px;border-radius:50%;` +
				'background-color:unset !important;z-index:9999;line-height:1;';

			const svgStyles = 'color:lightgray;background-color:unset !important;border-radius:50%;';

			const pathD = 'M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708' +
				'L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647' +
				'a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z';

			// eslint-disable-next-line no-unsanitized/property -- Templates escape all dynamic values; rich-text content is kses-sanitized on save
			this.greetingsBox.innerHTML = '<div class="ht_ctc_chat_greetings_box_layout" ' +
				`style="${boxLayoutStyles}">
				<span class="ctc_greetings_close_btn" style="${closeBtnStyles}">
					<svg style="${svgStyles}" xmlns="http://www.w3.org/2000/svg" ` +
						`width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
						<path d="${pathD}"/>
					</svg>
				</span>
				<div class="ctc_greetings_template template-${escapeAttr( templateId )}">` +
					`${html}</div>
			</div>`;

			this.greetingsBox.classList.remove( 'template-greetings-1', 'template-greetings-2' );
			this.greetingsBox.classList.add( `template-${templateId}` );
			this.greetingsBox.style.minWidth = minWidth;
			this.greetingsBox.style.left = '';
			this.greetingsBox.style.right = '';
			this.greetingsBox.style.setProperty( side2, '0px' );
			this.greetingsBox.style.display = 'block';
			this.greetingVisible = true;
		} catch ( error ) {
			log( 'Preview', `Greetings render failed for ${templateId}`, error );
			this.greetingsBox.style.display = 'none';
			this.greetingVisible = false;
		}

		return '';
	}
}
