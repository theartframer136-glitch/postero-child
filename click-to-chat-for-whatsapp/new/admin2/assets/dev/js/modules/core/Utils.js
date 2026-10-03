/**
 * Core Utilities
 */

/**
 * Debug Logger with Module Filtering
 *
 * Usage in code:
 *   log( 'App', 'Loading tab:', tabId );
 *   log( 'Settings', 'Save result:', data );
 *
 * Filter from browser console (on the fly, no code changes):
 *   ctcDebug('App')            → show only App logs
 *   ctcDebug('App,Settings')   → show App + Settings logs
 *   ctcDebug('*')              → show all logs (default)
 *   ctcDebug('')               → mute all logs
 *
 * Filter persists in sessionStorage (survives reload, clears on tab close).
 *
 * Production: __DEV__ is replaced with `false` by webpack DefinePlugin,
 * making all debug code dead code that Terser removes entirely.
 * Dev Mode: __DEV__ is undefined, so logging is active by default.
 */
/* global __DEV__ */
let _debugFilter = '*';

// Initialize debug infrastructure only in dev mode
if ( typeof __DEV__ !== 'undefined' && __DEV__ ) {
	_debugFilter = sessionStorage.getItem( 'ctc_debug' ) || '*';

	// Expose filter control to browser console
	window.ctcDebug = ( filter ) => {
		_debugFilter = ( typeof filter === 'string' ) ? filter : '*';
		sessionStorage.setItem( 'ctc_debug', _debugFilter );
		console.log( `CtC: Debug filter set to: ${_debugFilter || '(muted)'}` );
	};
}

/**
 * Debug log — fully eliminated in production builds.
 *
 * Usage:   log( 'App', 'Loading tab:', tabId );
 *
 * In dev mode: __DEV__ is true (defined via PHP inline script) → logs are active.
 * In production: Terser's pure_funcs removes all log() call sites,
 *                and __DEV__ = false makes the function a no-op.
 *
 * Filter from browser console:
 *   ctcDebug('App')           → show only App logs
 *   ctcDebug('App,Settings')  → show App + Settings logs
 *   ctcDebug('*')             → show all (default)
 *   ctcDebug('')              → mute all
 *
 * @param {string} module - Module name for filtering (e.g. 'App', 'Settings')
 * @param {...*} args - Values to log
 */
export const log = ( typeof __DEV__ !== 'undefined' && __DEV__ ) ?
	( module, ...args ) => {
		const filter = _debugFilter || '*';
		if ( filter === '*' || filter.split( ',' )
			.includes( module ) ) {
			console.log( `CtC: [${module}]`, ...args );
		}
	} :
	() => { /* noop */ };

const unsafeKeys = [
	'__proto__',
	'prototype',
	'constructor',
	'__defineGetter__',
	'__defineSetter__',
	'__lookupGetter__',
	'__lookupSetter__',
];

export const isSafeObjectKey = ( key ) => {
	return (
		typeof key === 'string' &&
		key.length > 0 &&
		/^[a-zA-Z0-9_-]+$/.test( key ) &&
		! unsafeKeys.includes( key )
	);
};

export const getSafeProperty = ( obj, key, fallback = undefined ) => {
	if (
		obj &&
		typeof obj === 'object' &&
		isSafeObjectKey( key ) &&
		Object.prototype.hasOwnProperty.call( obj, key )
	) {
		// todo: Reasearch on Reflect usage
		// return Reflect.get( obj, key );
		// eslint-disable-next-line security/detect-object-injection -- Key is validated by isSafeObjectKey above to prevent prototype pollution
		return obj[ key ];
	}
	return fallback;
};

/**
 * Escapes characters that can trigger XSS.
 * @param {string} str - The string to escape
 * @returns {string} The escaped string
 */
const _escapeDiv = typeof document !== 'undefined' ? document.createElement( 'div' ) : null;

export const escapeHTML = ( str ) => {
	if ( typeof str !== 'string' ) {
		return str;
	}

	// Prefer WP's built-in if available (wp-includes/js/dist/escape-html.min.js)
	if ( window.wp?.escapeHtml?.escapeHTML ) {
		return window.wp.escapeHtml.escapeHTML( str );
	}

	// Use DOM when available
	if ( _escapeDiv ) {
		_escapeDiv.textContent = str;
		return _escapeDiv.innerHTML;
	}

	return '';
};

/**
 * Whether the async Clipboard API is usable in this context.
 *
 * navigator.clipboard only exists in a secure context (https, localhost) — it is
 * undefined on plain-http admin pages (including *.local dev sites). Internal
 * helper used by copyToClipboard to decide between the async API and the
 * execCommand fallback.
 *
 * @returns {boolean}
 */
const canCopyToClipboard = () => !! ( navigator.clipboard && navigator.clipboard.writeText );

/**
 * Copy text to the clipboard, returning a Promise.
 *
 * Prefers the async Clipboard API (secure contexts). Falls back to a hidden
 * textarea + execCommand for non-secure http contexts (e.g. *.local dev sites)
 * where navigator.clipboard is undefined, so copy still works there.
 *
 * @param {string} text - Text to copy.
 * @returns {Promise<void>} Resolves on success, rejects if copying failed.
 */
export const copyToClipboard = ( text ) => {
	if ( canCopyToClipboard() ) {
		return navigator.clipboard.writeText( text );
	}

	// Non-secure context fallback.
	const ta = document.createElement( 'textarea' );
	ta.value = text;
	ta.style.position = 'fixed';
	ta.style.opacity = '0';
	document.body.appendChild( ta );
	ta.select();
	try {
		document.execCommand( 'copy' );
		return Promise.resolve();
	} catch ( err ) {
		return Promise.reject( err );
	} finally {
		ta.remove();
	}
};

/**
 * Returns a URL safe to drop into an href/src attribute.
 *
 * Restricts to http(s), mailto, tel, anchor (#…), and relative (/…) targets so a
 * stray `javascript:` or `data:` value from a misconfigured field cannot execute.
 * Non-strings, blanks, and disallowed schemes collapse to '#'.
 *
 * @param {*} url - Candidate URL.
 * @returns {string} The original URL, or '#' if unsafe.
 */
export const safeUrl = ( url ) => {
	if ( typeof url !== 'string' ) { return '#'; }
	const trimmed = url.trim();
	if ( ! trimmed ) { return '#'; }
	if ( /^(https?:|mailto:|tel:|#|\/)/i.test( trimmed ) ) { return trimmed; }
	return '#';
};

/**
 * Escapes characters for use within HTML attributes (like value="", class="", etc).
 * Similar to WordPress's esc_attr() function.
 * @param {string} str - The string to escape
 * @returns {string} The escaped string
 */
export const escapeAttr = ( str ) => {
	if ( typeof str !== 'string' ) {
		return str;
	}

	return str.replace( /[&<>"']/g, ( char ) => {
		switch ( char ) {
			case '&': return '&amp;';
			case '<': return '&lt;';
			case '>': return '&gt;';
			case '"': return '&quot;';
			case "'": return '&#39;';
			default: return char;
		}
	} );
};

/**
 * Safely decodes HTML entities (like &#x1f60a; or &amp;) into pure text.
 *
 * WARNING: Never use the output with .innerHTML — XSS risk.
 * Only use with .value or .textContent.
 *
 * Uses WP's htmlEntities if available, then DOMParser (RCDATA mode) as fallback.
 * <textarea> RCDATA mode decodes entities but treats <script>/<img> as plain text,
 * so only `</textarea>` itself needs escaping to prevent breakout.
 *
 * @param {string} str - The string containing HTML entities to decode
 * @returns {string} The decoded text string
 */
const _domParser = typeof DOMParser !== 'undefined' ? new DOMParser() : null;
const _decodeTextArea = typeof document !== 'undefined' ? document.createElement( 'textarea' ) : null;

export const decodeHTML = ( str ) => {
	if ( typeof str !== 'string' ) {
		return str;
	}

	// Prefer WP's built-in if available (wp-includes/js/dist/html-entities.min.js)
	if ( window.wp?.htmlEntities?.decodeEntities ) {
		return window.wp.htmlEntities.decodeEntities( str );
	}

	// Escape only `</textarea` to prevent breaking out of the DOMParser wrapper.
	const safeStr = str.replace( /<\/\s*textarea\s*>/gi, '&lt;/textarea&gt;' );

	if ( _domParser ) {
		const doc = _domParser.parseFromString( `<textarea>${safeStr}</textarea>`, 'text/html' );
		return doc.querySelector( 'textarea' ).value;
	}

	if ( _decodeTextArea ) {
		// eslint-disable-next-line no-unsanitized/property -- Safe: safeStr strips breakouts, textarea ignores scripts natively.
		_decodeTextArea.innerHTML = safeStr;
		return _decodeTextArea.value;
	}

	return str;
};

/**
 * Minimal wpautop: wraps double-newline separated chunks in <p>.
 *
 * Prefer WP's built-in if available (wp-includes/js/dist/autop.min.js).
 *
 * @param {string} str - The string to format.
 * @returns {string} The formatted string.
 */
export const autop = ( str ) => {
	if ( typeof str !== 'string' ) {
		return str;
	}
	const trimmed = str.trim();
	if ( trimmed === '' ) {
		return '';
	}

	// Prefer WP's built-in if available (wp-includes/js/dist/autop.min.js)
	if ( window.wp?.autop?.autop ) {
		return window.wp.autop.autop( trimmed );
	}

	if ( trimmed.startsWith( '<' ) ) {
		return trimmed;
	}

	return trimmed
		.split( /\n\s*\n/ )
		.map( ( part ) => `<p>${part.replace( /\n/g, '<br>' )}</p>` )
		.join( '' );
};

export const setSafeProperty = ( obj, key, value ) => {
	if (
		obj &&
		typeof obj === 'object' &&
		isSafeObjectKey( key ) &&
		(
			Object.prototype.hasOwnProperty.call( obj, key ) ||
			! ( key in obj )
		)
	) {
		// todo: Reasearch on Reflect usage
		// Reflect.set( obj, key, value );
		// eslint-disable-next-line security/detect-object-injection -- Key is validated by isSafeObjectKey above
		obj[ key ] = value;
		return true;
	}
	return false;
};

/**
 * Debounce a function to optimize rapid firing events.
 *
 * @param {Function} func The function to debounce
 * @param {number} wait Delay in milliseconds
 * @returns {Function} Debounced function
 */
export const debounce = ( func, wait = 100 ) => {
	let timeout;
	return function debouncedFunction ( ...args ) {
		clearTimeout( timeout );
		timeout = setTimeout( () => func.apply( this, args ), wait );
	};
};

/**
 * Retrieves a nested value from a configuration object based on WordPress option names.
 *
 * Problem: WordPress stores settings in flat variables (like `ht_ctc_chat_options`)
 * but represents deep structures in HTML names (like `ht_ctc_chat_options[group][field]`).
 * To get the value of `field` from a JS object, we need to parse that string path.
 *
 * This helper constructs the full path string and then splits it to traverse the object.
 *
 * Example 1 (Simple):
 * - obj: { ht_ctc_chat_options: { number: '123' } }
 * - optionGroup: 'ht_ctc_chat_options'
 * - fieldId: 'number'
 * -> constructs: 'ht_ctc_chat_options[number]' -> keys: ['ht_ctc_chat_options', 'number'] -> returns '123'
 *
 * Example 2 (Complex/Nested):
 * - obj: { ht_ctc_chat_options: { style: { mobile: 'show' } } }
 * - optionGroup: 'ht_ctc_chat_options'
 * - fieldId: 'style][mobile' (This is how WP stores deep keys in field IDs)
 * -> constructs: 'ht_ctc_chat_options[style][mobile]'
 * -> keys: ['ht_ctc_chat_options', 'style', 'mobile']
 * -> returns 'show'
 *
 * @param {object} obj: e.g. config.initialSettings i.e. ht_ctc_admin_var.initialSettings
 * @param {string} optionGroup: e.g. ht_ctc_chat_options
 * @param {string} fieldId: e.g. number,  ~channels][whatsapp][val
 * @returns {~string} value of the field
 */
export const getNestedValue = ( obj, optionGroup, fieldId ) => {
	if ( ! obj || ! optionGroup ) { return ''; }

	// Combine optionGroup and fieldId into a full path
	// e.g. group="ht_ctc_chat_options", id="channels][whatsapp][val" -> "ht_ctc_chat_options[channels][whatsapp][val]"
	// Or if fieldId is simple "number" -> "ht_ctc_chat_options[number]"
	const fullPath = `${optionGroup}[${fieldId}]`;

	const keys = fullPath.split( /\[|\]/ )
		.filter( key => key );

	let current = obj;
	for ( const key of keys ) {
		current = getSafeProperty( current, key );
		if ( ! current ) { return ''; }
	}

	return ( current !== undefined ) ? current : '';
};

/**
 * Set a nested value in an object based on an array of keys.
 *
 * @param {Object} obj - The object to modify.
 * @param {Array<string>} keys - Array of keys representing the path to the nested property.
 * @param {*} value - The value to set at the nested property.
 */
export const setNestedValue = ( obj, keys, value ) => {
	let current = obj;
	keys.forEach( ( key, index ) => {
		if ( index === keys.length - 1 ) {
			setSafeProperty( current, key, value );
		} else {
			if ( ! getSafeProperty( current, key ) ) {
				setSafeProperty( current, key, {} );
			}
			current = getSafeProperty( current, key );
		}
	} );
};

/**
 * Replace {placeholders} inside a content string.
 *
 * This utility replaces tokens formatted as `{key}` using either:
 * - Field-level variables
 * - Global runtime configuration (`window.ht_ctc_admin_var`)
 *
 * @param {string} content
 *        A string containing placeholders like `{wpTime}` or `{option_group}`.
 *
 * @param {Object|Array|string|boolean} [variables=true]
 *        Controls how placeholders are resolved.
 *
 *        Supported formats:
 *
 *        • true
 *          Allow all runtime config keys.
 *          Example:
 *              content:  "Time: {wpTime}"
 *              variables: true
 *              → resolves using config.wpTime
 *
 *        • Object (placeholder → value OR configKey)
 *          Example (literal override):
 *              variables: { first_name: "Click to Chat" }
 *              → {first_name} → "Click to Chat"
 *
 *          Example (mapping to config key):
 *              variables: { site_time: "wpTime" }
 *              → {site_time} → config.wpTime
 *
 *        • Array of config keys
 *          Example:
 *              variables: ["wpTime", "version"]
 *              → Only those placeholders resolve from config
 *
 *        • String (single config key)
 *          Example:
 *              variables: "wpTime"
 *              → Only {wpTime} resolves from config
 *
 * Resolution Order:
 *   1. Field literal override (object value)
 *   2. Field mapping to config key (object value referencing config)
 *   3. variables === true (allow all config keys)
 *   4. Selected config keys (array/string)
 *   5. Leave placeholder unchanged if no match found
 *
 * @returns {string}
 *          The content string with placeholders replaced.
 */

const runtime = {
	...( window.ht_ctc_admin_var || {} ),
	...( window.ht_ctc_admin_var?.initialSettings?.ht_ctc_chat_options || {} ),

	// ...future sources go here
	// ...anotherConfigObject
};
export const applyVariables = ( content, variables = true ) => {
	if ( ! content || typeof content !== 'string' ) {
		return content;
	}

	let result = content;

	// 1. Process custom variables if provided as object
	if ( variables && typeof variables === 'object' && ! Array.isArray( variables ) ) {
		Object.entries( variables )
			.forEach( ( [ key, value ] ) => {
				const placeholder = `{${key}}`;
				result = result.split( placeholder )
					.join( value );
			} );
	}

	// 2. Preset global variables
	if ( variables ) {
		// match: {wpCurrentTime}, key: wpCurrentTime, {wpCurrentTime} replaced with 2026-03-04 11:58:41
		// match: {wpTimeZone}, key: wpTimeZone, {wpTimeZone} replaced with +05:30
		// match: {abc}, key: abc, {abc} not found in runTime to replace
		result = result.replace( /\{(\w+)\}/g, ( match, key ) => {
			const val = getSafeProperty( runtime, key );
			log( 'Utils', 'applyVariables()', '\n', `match: ${match}, key: ${key}, ${val ? `${match} replaced with ${val}` : `${match} not found in runTime to replace`}` );
			return val !== undefined ? val : match;
		} );
	}

	return result;
};

/**
 * Translate a technical fetch/REST error into a short, human-friendly hint.
 *
 * Single source of truth for the network/API error copy shown by both the
 * field-load failure UI (App.loadTabSettings) and the save-error toast
 * (UIManager 'settings:error'), so every API failure surfaces consistent
 * guidance. Matches on the error message text produced by API.request()
 * (status-tagged `[4xx]`, our `timed out` abort, JSON-parse failures) and the
 * browser's native fetch errors. Falls back to the raw message when nothing
 * matches — callers that also show a separate "technical detail" line can keep
 * doing so.
 *
 * @param {Error|string} error - The caught error (or its message).
 * @returns {string} A user-facing message.
 */
export const friendlyErrorMessage = ( error ) => {
	const message = ( error && error.message ) ? error.message : String( error || '' );

	if ( ! message ) { return 'An unknown error occurred.'; }

	// Network is down or the site is unreachable.
	if ( message.includes( 'Failed to fetch' ) || message.includes( 'NetworkError' ) ) {
		return 'Unable to connect to the server. Please check your internet connection or if the site is reachable.';
	}

	// Our own per-attempt timeout (see API.request) — server too slow / unresponsive.
	if ( message.includes( 'timed out' ) ) {
		return 'The server took too long to respond. Please try again in a moment.';
	}

	// Expired WP REST nonce (session expired). Never reload automatically —
	// tell the user to reload so it stays their call.
	if ( message.includes( 'rest_cookie_invalid_nonce' ) || message.includes( 'rest_nonce' ) ) {
		return 'Your session has expired. Please reload this page and save again (reloading will discard unsaved changes on this screen).';
	}

	// 401/403 where the error body wasn't WordPress JSON — the request was
	// blocked before reaching WordPress (hosting firewall / ModSecurity / WAF).
	if ( ( message.includes( '[403]' ) || message.includes( '[401]' ) ) && message.includes( 'Non-JSON error response' ) ) {
		return 'The server blocked this request before it reached WordPress — usually a hosting firewall or security module (ModSecurity/WAF), often triggered by URLs or code in the settings. Please contact your hosting support with the details below.';
	}

	// Other WordPress-side 401/403 (capability / security plugin denial).
	if ( message.includes( '[403]' ) || message.includes( '[401]' ) ) {
		return 'Security verification failed. Please reload the page and try again. If it keeps happening, a security plugin or user-role restriction may be blocking the request.';
	}

	// Server returned HTML / non-JSON (PHP error or conflict with another plugin).
	if ( message.includes( 'Unexpected token' ) || message.includes( 'Invalid or empty JSON' ) ) {
		return 'The server returned an invalid response. This is often caused by a PHP error or conflict with another plugin.';
	}

	// Fallback: surface the raw message.
	return message;
};

/**
 * Wrapper to safely execute a function and catch errors without crashing the app.
 * @param {Function} fn - Function to execute
 * @param {string} context - Name of the feature/context for logging
 */
export const safeRun = ( fn, context = 'Feature' ) => {
	try {
		fn();
		return true;
	} catch ( error ) {
		console.error( `CTC: safeRun: ${context} failed:`, error );

		// Dispatch error event so UI can show a specific warning icon if needed
		document.dispatchEvent( new CustomEvent( 'ht_ctc_error', { detail: { context, error } } ) );
		return false;
	}
};

/**
 * Applies conditional display attributes to a DOM element based on field configuration.
 *
 * @param {HTMLElement} element - The DOM element to apply attributes to.
 * @param {Object} field - The field configuration object containing data_watch, etc.
 */
export const applyConditionalAttributes = ( element, field ) => {
	if ( ! element || ! field ) {
		return;
	}

	// data-watch attributes. (handles from Conditions.js)
	if ( field.data_watch ) {
		element.setAttribute( 'data-watch', field.data_watch );

		if ( field.data_show_on_change ) {
			element.setAttribute( 'data-show-on-change', field.data_show_on_change );
		}

		if ( field.data_show_when !== undefined ) {
			element.setAttribute( 'data-show-when', field.data_show_when );
		} else if ( ! field.data_hide_when && ! field.data_show_on_change ) {
			element.setAttribute( 'data-show-when', '1' );
		}

		if ( field.data_hide_when ) {
			element.setAttribute( 'data-hide-when', field.data_hide_when );
		}
	}

	// Action on change (handles from Actions.js)
	if ( field.action_onchange ) {
		element.setAttribute( 'data-action-onchange', field.action_onchange );
	}

	// Action on click (handles from Actions.js)
	if ( field.action_onclick ) {
		element.setAttribute( 'data-action-onclick', field.action_onclick );
	}

	// // Validation
	// if ( field.validation ) {
	// 	element.setAttribute( 'data-validation', field.validation );
	// }
};

/**
 * Dynamically import a module with retry logic.
 * @param {Function} importFn - A function returning a dynamic import promise.
 * @param {number} retries - Number of retries left.
 * @param {number} delay - Delay between retries in milliseconds.
 * @returns {Promise<any>}
 */
export const importWithRetry = async ( importFn, retries = 3, delay = 1000 ) => {
	try {
		return await importFn();
	} catch ( error ) {
		if ( retries > 0 ) {
			if ( ! navigator.onLine ) {
				log( 'Utils', 'Network offline. Waiting for connection to resume for module import...' );

				document.dispatchEvent( new CustomEvent( 'ht_ctc_show_toast', {
					detail: {
						title: 'Network Offline',
						description: 'Waiting for connection to resume...',
						iconClass: 'dashicons dashicons-warning',
						iconColor: '#f56e28',
						duration: 60000,
					},
				} ) );

				await new Promise( resolve => {
					const onOnline = () => {
						window.removeEventListener( 'online', onOnline );
						resolve();
					};
					window.addEventListener( 'online', onOnline );
				} );
				log( 'Utils', 'Network restored. Retrying module import immediately...' );

				document.dispatchEvent( new CustomEvent( 'ht_ctc_show_toast', {
					detail: {
						title: 'Network Restored',
						description: 'Resuming operations...',
						iconClass: 'dashicons dashicons-saved',
						iconColor: '#46b450',
						duration: 3000,
					},
				} ) );

				// Retry without consuming retry count
				return importWithRetry( importFn, retries, delay );
			}

			log( 'Utils', `Module import failed, retrying in ${delay}ms... (${retries} retries left)`, error );
			await new Promise( resolve => setTimeout( resolve, delay ) );
			return importWithRetry( importFn, retries - 1, delay * 1.5 );
		}
		throw error;
	}
};

/*
// Future WP/Web API Fallback Helpers - Uncomment when needed:

/**
 * Accessible Screen Reader Announcements.
 *
 * @param {string} message - The message to announce.
 * @param {string} [ariaLive='polite'] - Announcement priority.
 * /
export const speak = ( message, ariaLive = 'polite' ) => {
	if ( window.wp?.a11y?.speak ) {
		window.wp.a11y.speak( message, ariaLive );
	}
};

/**
 * Extensible Filter Hooks.
 *
 * @param {string} hookName - The name of the filter hook.
 * @param {*} value - The value to filter.
 * @param {...*} args - Additional arguments passed to the callback.
 * @returns {*} The filtered value.
 * /
export const applyFilters = ( hookName, value, ...args ) => {
	if ( window.wp?.hooks?.applyFilters ) {
		return window.wp.hooks.applyFilters( hookName, value, ...args );
	}
	return value;
};

/**
 * Clean URL parameter appending with fallback.
 *
 * @param {string} url - Base URL.
 * @param {Object} args - Key-value map of query parameters to add.
 * @returns {string} The formatted URL.
 * /
export const addQueryArg = ( url, args ) => {
	if ( window.wp?.url?.addQueryArgs ) {
		return window.wp.url.addQueryArgs( url, args );
	}
	try {
		const urlObj = new URL( url, window.location.origin );
		Object.entries( args ).forEach( ( [ key, val ] ) => urlObj.searchParams.set( key, val ) );
		return url.startsWith( '/' ) ? urlObj.pathname + urlObj.search : urlObj.toString();
	} catch {
		return url;
	}
};

/**
 * Safe HTML Stripping with DOM/Regex fallbacks.
 *
 * @param {string} html - HTML content.
 * @returns {string} Stripped plain text.
 * /
export const stripHTML = ( html ) => {
	if ( typeof html !== 'string' ) {
		return html;
	}
	if ( window.wp?.sanitize?.stripHTML ) {
		return window.wp.sanitize.stripHTML( html );
	}
	if ( typeof document !== 'undefined' ) {
		const doc = new DOMParser().parseFromString( html, 'text/html' );
		return doc.body.textContent || '';
	}
	return html.replace( /<\/?[^>]+(>|$)/g, '' );
};

/**
 * Keyboard navigation key check.
 *
 * @param {KeyboardEvent} event - Keydown event.
 * @param {string} keyName - Name of the key (e.g. 'ESCAPE', 'ENTER').
 * @returns {boolean} True if matches.
 * /
export const isKeyboardKey = ( event, keyName ) => {
	if ( window.wp?.keycodes?.isKeyboardEvent ) {
		return window.wp.keycodes.isKeyboardEvent( event, keyName );
	}
	const mapping = {
		ESCAPE: 'Escape',
		ENTER: 'Enter',
		SPACE: ' ',
	};
	const normalizedKey = mapping[ keyName ] || keyName;
	return event.key === normalizedKey;
};
*/
