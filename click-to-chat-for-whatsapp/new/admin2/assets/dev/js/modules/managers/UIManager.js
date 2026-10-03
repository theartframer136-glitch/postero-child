/**
 * UI Manager
 * Handles global UI interactions that aren't specific to a single component.
 *
 * Responsibilities:
 * 1. Global Toasts/Notifications
 * 2. Syncing visible inputs with hidden form fields (for Settings API)
 * 3. Handling UI-specific interactions like Grid selection
 */
import { log, friendlyErrorMessage, copyToClipboard } from '../core/Utils.js';
export default class UIManager {

	static init ( app ) {
		log( 'UI', 'Initializing...' );
		this.app = app;

		// Subscribe to global toast events
		document.addEventListener( 'ht_ctc_show_toast', ( event ) => {
			if ( event.detail ) {
				this.showToast( event.detail );
			}
		} );

		// Listen for app-level events
		const events = this.app.events;
		if ( events ) {
			events.on( 'ht_ctc_show_toast', ( data ) => {
				this.showToast( data );
			} );

			events.on( 'settings:saved', ( payload ) => {
				this.clearSaveErrorState();
				this.showToast( {
					title: this.app.config.i18n.saved || 'Settings Saved',

					// SettingsManager passes a "what changed" summary
					// (field labels); fall back to the generic line.
					description: payload?.summary || 'Your changes were successfully saved.',
					iconClass: 'dashicons dashicons-yes-alt',
					iconColor: 'green',
				} );
			} );

			events.on( 'settings:error', ( error ) => {
				// Shared technical→friendly mapping (see Utils.friendlyErrorMessage),
				// so a save failure reads the same as a field-load failure instead of
				// dumping the raw "[500]" / "Unexpected token" message.
				const friendly = friendlyErrorMessage( error );
				const raw = ( error && error.message ) ? error.message : '';

				// Always surface the exact technical message alongside the friendly
				// hint — it's what support needs to diagnose (status code, REST error
				// code, firewall block). Longer duration so it can be read/copied.
				const description = ( raw && raw !== friendly ) ?
					`${friendly} — Details: ${raw}` :
					friendly;

				this.showToast( {
					title: this.app.config.i18n.error || 'Error saving settings.',
					description,
					iconClass: 'dashicons dashicons-warning',
					iconColor: 'red',
					duration: 12000,
				} );

				// Toasts are transient — also keep a persistent "Save failed"
				// indicator next to the save button until the next successful save.
				this.showSaveErrorState( friendly, raw );
			} );
		}

		// ~ Start listening for changes on inputs marked with 'update-hidden-field'
		// ~ This bridges the UI inputs (toggle switches) -> Actual Hidden Form Inputs
		this.listenerForInputChanges();

		// Initialize physical on-page offline/online warning box
		this.initOfflineDetection();
	}

	/**
	 * Show a persistent "Save failed" indicator next to the save button.
	 *
	 * Toasts disappear; this stays until the next successful save so the user
	 * can re-read the friendly hint and copy the exact technical message into
	 * a support ticket. Clicking the badge toggles a details popover.
	 *
	 * The DOM is built once (buildSaveErrorState) and reused: repeat failures
	 * only update the text nodes, and a successful save just hides it.
	 * All content is set via textContent (server messages are untrusted);
	 * styling lives in components/save-error.css (theme-aware).
	 *
	 * @param {string} friendly Human-friendly hint (Utils.friendlyErrorMessage).
	 * @param {string} raw      Exact technical error message.
	 */
	static showSaveErrorState ( friendly, raw ) {
		if ( ! this.saveError && ! this.buildSaveErrorState() ) { return; }

		const ui = this.saveError;
		const time = new Date()
			.toLocaleTimeString();

		ui.time = time;
		ui.friendly = friendly || 'Unknown error.';
		ui.raw = ( raw && raw !== friendly ) ? raw : '';

		ui.title.textContent = `Last save failed at ${time}`;
		ui.hint.textContent = ui.friendly;
		ui.detail.textContent = ui.raw;
		ui.detail.style.display = ui.raw ? '' : 'none';
		ui.copyBtn.style.display = ui.raw ? '' : 'none';
		ui.copyBtn.textContent = 'Copy details';

		ui.wrap.classList.add( 'visible' );

		// Open the details popover automatically so the user sees the failure
		// without having to discover the badge is clickable. The outside-click
		// listener (buildSaveErrorState) hides it again on any click elsewhere.
		this.toggleSaveErrorPop( true );
	}

	/**
	 * Build the save-error badge/popover DOM and cache element refs.
	 * Runs at most once per page load, on the first save failure.
	 *
	 * @returns {boolean} False when the save button isn't on the page.
	 */
	static buildSaveErrorState () {
		const saveBtn = document.getElementById( 'save-button' );
		if ( ! saveBtn || ! saveBtn.parentNode ) { return false; }

		const wrap = document.createElement( 'span' );
		wrap.id = 'ctc-save-error-state';
		wrap.className = 'ctc-save-error';

		const badge = document.createElement( 'button' );
		badge.type = 'button';
		badge.className = 'ctc-save-error-badge';
		badge.setAttribute( 'aria-expanded', 'false' );
		badge.title = 'Last save failed — click for details';

		// badge.setAttribute( 'data-tip', 'Last save failed — click for details' );
		// badge.setAttribute( 'data-tip-pos', 'bottom' );
		badge.innerHTML = '<span class="dashicons dashicons-warning"></span>';
		badge.appendChild( document.createTextNode( 'Save failed' ) );

		const pop = document.createElement( 'div' );
		pop.className = 'ctc-save-error-pop';

		const title = document.createElement( 'p' );
		title.className = 'ctc-save-error-title';

		const hint = document.createElement( 'p' );
		hint.className = 'ctc-save-error-hint';

		const detail = document.createElement( 'code' );
		detail.className = 'ctc-save-error-detail';

		const copyBtn = document.createElement( 'button' );
		copyBtn.type = 'button';
		copyBtn.className = 'button button-small';
		copyBtn.textContent = 'Copy details';
		copyBtn.addEventListener( 'click', () => {
			const ui = this.saveError;
			const text = `Click to Chat save failed at ${ui.time}\n${ui.friendly}\n${ui.raw}`;

			// Shared helper — falls back to execCommand on non-secure/.local
			// contexts where navigator.clipboard is undefined.
			copyToClipboard( text )
				.then( () => { copyBtn.textContent = 'Copied!'; } )
				.catch( () => { copyBtn.textContent = 'Copy failed'; } );
		} );

		badge.addEventListener( 'click', ( event ) => {
			event.stopPropagation();
			this.toggleSaveErrorPop();
		} );

		// Single outside-click close listener for the life of the page —
		// cheap no-op while the popover is closed.
		document.addEventListener( 'click', ( event ) => {
			if ( wrap.classList.contains( 'open' ) && ! wrap.contains( event.target ) ) {
				this.toggleSaveErrorPop( false );
			}
		} );

		pop.append( title, hint, detail, copyBtn );
		wrap.append( badge, pop );
		saveBtn.parentNode.insertBefore( wrap, saveBtn.nextSibling );

		this.saveError = { wrap, badge, title, hint, detail, copyBtn, time: '', friendly: '', raw: '' };
		return true;
	}

	/**
	 * Open/close the save-error details popover.
	 *
	 * @param {boolean} [open] Force state; omit to toggle.
	 */
	static toggleSaveErrorPop ( open ) {
		const ui = this.saveError;
		if ( ! ui ) { return; }
		const show = ( typeof open === 'boolean' ) ? open : ! ui.wrap.classList.contains( 'open' );
		ui.wrap.classList.toggle( 'open', show );
		ui.badge.setAttribute( 'aria-expanded', show ? 'true' : 'false' );
	}

	/**
	 * Hide the save-error indicator (on the next successful save).
	 */
	static clearSaveErrorState () {
		if ( this.saveError ) {
			this.saveError.wrap.classList.remove( 'visible' );
			this.toggleSaveErrorPop( false );
		}
	}

	static initOfflineDetection () {
		const updateOfflineBanner = () => {
			let banner = document.getElementById( 'ctc-offline-banner' );
			if ( ! navigator.onLine ) {
				if ( ! banner ) {
					banner = document.createElement( 'div' );
					banner.id = 'ctc-offline-banner';
					banner.innerHTML = `
						<div style="
							display: flex;
							align-items: center;
							justify-content: center;
							padding: 12px;
							background-color: #fcf0f1;
							border-left: 4px solid #d63638;
							margin: 15px 0;
							border-radius: 4px;
							box-shadow: 0 1px 2px rgba(0,0,0,.05);
						">
							<span class="dashicons dashicons-warning"
							style="color: #d63638; margin-right: 8px;"></span>

							<p style="margin: 0;">
								<strong>Network Offline:</strong>
								Waiting for connection.
								Your actions are paused and will resume automatically.
							</p>
						</div>
					`;

					// Insert at the top of the main container wrap or header
					const targetContainer = document.querySelector( '.ht-ctc-admin-main-wrap' ) || document.querySelector( '.wrap' );
					if ( targetContainer ) {
						targetContainer.insertBefore( banner, targetContainer.firstChild );
					} else {
						document.body.appendChild( banner );
					}
				}
			} else {
				if ( banner ) {
					// Optional: Show a quick "Restored" success state before removing
					banner.innerHTML = `
						<div style="
							display: flex;
							align-items: center;
							justify-content: center;
							padding: 12px;
							background-color: #edfaee;
							border-left: 4px solid #46b450;
							margin: 15px 0;
							border-radius: 4px;
							box-shadow: 0 1px 2px rgba(0,0,0,.05);
						">
							<span class="dashicons dashicons-saved"
							style="color: #46b450; margin-right: 8px;"></span>

							<p style="margin: 0;">
								<strong>Network Restored:</strong>
								Operations are resuming.
							</p>
						</div>
					`;

					// Remove the banner after 3 seconds
					setTimeout( () => {
						if ( banner.parentNode ) {
							banner.parentNode.removeChild( banner );
						}
					}, 3000 );
				}
			}
		};

		window.addEventListener( 'offline', updateOfflineBanner );
		window.addEventListener( 'online', updateOfflineBanner );

		// Run once on load to verify current state
		updateOfflineBanner();
	}

	static showToast ( {
		title = '',
		description = '',
		iconClass = 'dashicons dashicons-yes-alt',
		iconColor = '',
		duration = 3000,
		action = null,
	} = {} ) {
		const toast = document.getElementById( 'toast' );
		if ( ! toast ) { return; }

		// 1. Reset: Clear timers & Force Animation Restart (Reflow)
		// (also cancel a pending 'toast:hidden' emit from a toast that is
		// currently sliding out — this new toast owns the corner again).
		if ( this.toastTimeout ) { clearTimeout( this.toastTimeout ); }
		if ( this.toastHiddenTimeout ) { clearTimeout( this.toastHiddenTimeout ); }
		toast.classList.remove( 'show' );
		void toast.offsetWidth;

		// 2. Update Content
		const updateText = ( selector, text ) => {
			const el = toast.querySelector( selector );
			if ( el ) { el.textContent = text; }
		};

		updateText( '.toast-title', title );
		updateText( '.toast-description', description );

		// Optional action link (e.g. a PRO upgrade CTA). Hidden for normal toasts.
		const actionEl = toast.querySelector( '.toast-action' );
		if ( actionEl ) {
			if ( action && action.text && action.url ) {
				updateText( '.toast-action-text', action.text );
				actionEl.href = action.url;
				actionEl.style.display = '';
			} else {
				actionEl.style.display = 'none';
				actionEl.removeAttribute( 'href' );
			}
		}

		const icon = toast.querySelector( '.toast-content .dashicons' );
		if ( icon ) {
			icon.className = iconClass;
			icon.style.color = iconColor;
		}

		// 3. Sync Animation & Show
		const progress = toast.querySelector( '.toast-progress' );
		if ( progress ) {
			progress.style.animationDuration = `${duration}ms`;
		}

		toast.classList.add( 'show' );

		// Announce the toast lifecycle so other modules can react — the live
		// Preview floats at the widget position and can overlap the toast, so
		// it hides on 'toast:show' and re-renders itself on 'toast:hidden'.
		this.app?.events?.emit( 'toast:show' );

		this.toastTimeout = setTimeout( () => {
			toast.classList.remove( 'show' );

			// Announce 'toast:hidden' only after the slide-out transition
			// finishes (0.3s in toast.css) — emitting immediately would bring
			// the preview back under the still-animating toast.
			this.toastHiddenTimeout = setTimeout( () => {
				this.app?.events?.emit( 'toast:hidden' );
			}, 300 );
		}, duration );
	}

	/**
	 * Updates a hidden input field with a new value and triggers auto-save.
	 *
	 * @param {string} targetSelector - The ID or CSS Query Selector of the hidden input field to update.
	 * @param {string} newValue - The new value to set.
	 */
	static updateTargetInput ( targetSelector, newValue ) {
		const input = document.querySelector( targetSelector );

		if ( ! input ) {
			// console.error( `Input with selector "${targetSelector}" not found.` );
			return;
		}

		// Update value and mark as changed
		if ( input.value !== newValue ) {
			input.value = newValue;
			input.dataset.changed = 'true';

			// Trigger a change event so conditional logic (data-watch) will catch it
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );

			// Trigger auto-save if available via Event Bus
			this.app.events?.emit( 'field:dirty', input );
		}
	}

	/**
	 * Sets up a global listener for input changes to sync UI controls with hidden fields.
	 *
	 * Concept: "UI Bridge" or "Data Sync"
	 * Purpose: Connects a UI control (like a fancy switch, custom dropdown, or other interactive element)
	 *          to a hidden form field that actually stores and submits the data.
	 *
	 * How it works:
	 * 1. A UI element (source) has the class `ctc-sync-source-change`.
	 * 2. It also has a `data-sync-target` attribute pointing to the ID of the hidden field.
	 * 3. When the source changes, this listener updates the hidden field's value.
	 * 4. Auto-save is then triggered on the hidden field.
	 */
	static listenerForInputChanges () {

		// Use Event Delegation on the document to handle dynamically added elements.
		document.addEventListener( 'change', ( event ) => {

			// Check if the changed element is a sync source
			const sourceInput = event.target.closest( '.ctc-sync-source-change' );
			if ( ! sourceInput ) { return; }

			// Get the target selector from data attributes i.e. data-sync-target="some-element-id"
			const targetSelector = sourceInput.dataset.syncTarget;
			if ( ! targetSelector ) { return; }

			// Determine value to sync
			let newValue = sourceInput.value;

			// Special handling for Checkboxes
			if ( sourceInput.type === 'checkbox' ) {
				// If checked, use the checkbox's value (default 'on'), or '1' if value is missing but usually it has a value.
				// If unchecked, use empty string or a specific "unchecked value" if defined.
				const checkedValue = sourceInput.value || '1';
				const uncheckedValue = sourceInput.getAttribute( 'data-unchecked-value' ) || '';

				newValue = sourceInput.checked ? checkedValue : uncheckedValue;
			}

			// Update the hidden field
			this.updateTargetInput( targetSelector, newValue );
		} );

		// 3. Grid Option Click Listener
		// Handles selection logic for custom Grid options (visual radio replacements).
		document.addEventListener( 'click', ( event ) => {
			// A. Trigger for .ctc-sync-source-click elements
			// These are elements (like buttons or divs) that act as inputs when clicked.
			// Exclude .is-locked options: they share this class but are display-only with no
			// real value (data-value="undefined"); syncing would corrupt the target input.
			// They are handled by the .grid-option branch below.
			const sourceClick = event.target.closest( '.ctc-sync-source-click:not(.is-locked)' );

			if ( sourceClick ) {
				const targetSelector = sourceClick.dataset.syncTarget;
				if ( targetSelector ) {
					// Use value attribute or data-value
					const newValue = sourceClick.value || sourceClick.dataset.value;
					this.updateTargetInput( targetSelector, newValue );
				}
			}

			// B. Trigger for .grid-option elements
			const gridOption = event.target.closest( '.grid-option' );
			if ( gridOption ) {
				// Locked options are display-only — ignore selection clicks. PRO-locked
				// options additionally surface an upgrade hint via the toast.
				if ( gridOption.classList.contains( 'is-locked' ) ) {
					if ( gridOption.classList.contains( 'pro-option' ) ) {
						this.showToast( {
							title: 'A PRO feature',
							description: 'Unlock this and more with Click to Chat PRO.',
							iconClass: 'dashicons dashicons-star-filled',
							iconColor: 'var(--pro-color)',
							duration: 6000,
							action: {
								text: 'Upgrade to PRO',
								url: 'https://holithemes.com/plugins/click-to-chat/pricing/',
							},
						} );
					}
					return;
				}

				// Get value from data-value attribute
				const gridOptionValue = gridOption.getAttribute( 'data-value' );
				if ( gridOptionValue === null ) { return; }

				// Find the mainGrid container (.grid)
				const mainGrid = gridOption.closest( '.grid' );
				if ( ! mainGrid ) { return; }

				// UI Update: Remove selected class from siblings, add to clicked
				mainGrid.querySelectorAll( '.grid-option' )
					.forEach( opt => {
						const selected = opt === gridOption;
						opt.classList.toggle( 'selected', selected );
						opt.setAttribute( 'aria-pressed', selected ? 'true' : 'false' );
					} );

				// Note: Actual data syncing is often handled by a separate hidden input or
				// if the grid option itself has data-sync-target via the 'ctc-sync-source-click' class handled above.
				// If specific grid logic is needed here, it can be added.
			}
		} );
	}

}
