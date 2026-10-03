/* global intlTelInput */
// Provided by intl-tel-input library (loaded at class-ht-ctc-admin-scripts.php).

/**
 * International Phone Input Logic
 */

import { getCtcStorageItem, setCtcStorageItem } from '../core/Storage.js';
import { log } from '../core/Utils.js';

/**
 * Initialize Intl Input
 * - calling intl_init('intl_number')
 * - calling intl_onchange(context)
 *
 * @param {Object} app
 * @returns {void}
 */
export const initIntlInput = ( className = 'intl_number', context = document, app = null ) => {
	const currentApp = app || window.HTCtcAdminApp;
	try {
		if ( ! context || typeof context.querySelector !== 'function' ) {
			return;
		}

		if ( context.querySelector( '.' + className ) ) {
			if ( typeof intlTelInput !== 'undefined' ) {
				// Initialize each element
				const elements = context.querySelectorAll( '.' + className );

				elements.forEach( ( element ) => {
					intl_init( element );
				} );

				// Attach change handlers
				intl_onchange( context, currentApp );
			} else {
				log( 'IntlInput', 'intlTelInput not loaded' );
			}
		}
	} catch ( error ) {
		log( 'IntlInput', 'initIntlInput error:', error );
	}
};

// Helper to initialize a single element
const intl_init = ( element ) => {
	try {
		if ( ! element || ! ( element instanceof Element ) ) { return; }

		// Prevent Double Initialization
		// Check if the element already has the 'iti-loaded' class or an existing instance.
		if ( element.classList.contains( 'iti-loaded' ) ) {
			try {
				const existingInstance = intlTelInput.getInstance( element );
				if ( existingInstance ) {
					return existingInstance;
				}

				// If no instance but has class, remove class to allow re-init
				element.classList.remove( 'iti-loaded' );
			} catch {
				// getInstance failed, remove marker and continue
				element.classList.remove( 'iti-loaded' );
			}
		}

		element.classList.add( 'iti-loaded' );

		let intl = null;

		// ... (rest of logic)

		// 1. Get current value
		// Fix the literal DOM attribute so the library's internal detection sees the '+' prefix.
		// const attr_value = element.getAttribute( 'value' ) || '';
		let attr_value = ( element.hasAttribute( 'value' ) ? element.getAttribute( 'value' ) : element.value ) || '';

		if ( attr_value ) {
			element.value = ! attr_value.startsWith( '+' ) ? `+${attr_value}` : attr_value; // Updates the live property
			element.setAttribute( 'value', element.value ); // Updates the HTML attribute
			attr_value = element.value;
		}

		// 2. Identify Hidden Input (Actual data storage)
		// The visible input is just for the user interface. The actual number is stored in a hidden input.
		const dataName = element.getAttribute( 'data-name' );
		const hidden_input_name = dataName || 'ht_ctc_chat_options[number]';
		element.removeAttribute( 'name' ); // Remove name to exclude from form data. (hidden value is main)

		// Mark as a UI-only input so SettingsManager.markChanged() ignores init-time
		// events fired by intlTelInput (e.g. during setNumber / countrychange).
		// Real dirty tracking flows through the hidden input via triggerAutoSave (guarded by userInteracted).
		element.dataset.ctcNoTrack = 'true';

		// 3. Determine Country Code
		// Priority: Cached -> Date Checked -> Fallback
		const country_code_date = new Date()
			.toDateString();
		let country_code = '';

		try {
			const storedDate = getCtcStorageItem( 'country_code_date' );
			if ( storedDate === country_code_date ) {
				country_code = getCtcStorageItem( 'country_code' );
			}
		} catch { country_code = ''; }

		// 4. Initialize Plugin
		const call_intl = () => {
			try {
				// Get Preferred Countries (Recent selections)
				let pre_countries = [];
				try {
					const storedPre = getCtcStorageItem( 'pre_countries' );
					if ( Array.isArray( storedPre ) ) { pre_countries = storedPre; }
				} catch { pre_countries = []; }

				// Configuration
				const configUtilsPath = ( window.ht_ctc_admin_var && window.ht_ctc_admin_var.paths && window.ht_ctc_admin_var.paths.intlTelInputUtils ) || '';

				const values = {
					autoHideDialCode: false,
					initialCountry: 'auto',
					geoIpLookup: ( success, failure ) => {
						try {
							success( country_code || 'us' );
						} catch { if ( typeof failure === 'function' ) { failure(); } }
					},
					dropdownContainer: document.body,
					hiddenInput: () => {
						return {
							phone: hidden_input_name,

							// country: 'ht_ctc_chat_options[intl_country]',
						};
					},
					nationalMode: false,

					// autoPlaceholder: 'polite',
					countryOrder: pre_countries,
					separateDialCode: true,
					containerClass: 'intl_tel_input_container',
					utilsScript: configUtilsPath,
				};

				intl = intlTelInput( element, values );

				// Fix: Input display issue – auto-parsing fails for certain numbers
				// (value is saved and retrieved correctly from DB)
				if ( attr_value && attr_value.length > 8 ) {
					intl.setNumber( attr_value );
				}

				// Get the hidden input and add intl_number_hidden class to it (after save, we will use this class to update the form value both hidden and visible intl input)
				const hiddenInput = intl.hiddenInput || element.closest( '.intl_tel_input_container' )
					.querySelector( 'input[type="hidden"]' );

				// const hiddenInput = element.closest('.intl_tel_input_container').querySelector('input[type="hidden"]');
				hiddenInput.classList.add( 'intl_number_hidden' );

				// Set the value of the hidden input. (as most/save logic runs on js. will update hidden input value)
				intl.promise.then( () => {
					const value = intl.getNumber();

					if ( hiddenInput && value ) {
						hiddenInput.value = value;
					}
				} )
					.catch( ( err ) => {
						log( 'IntlInput', 'Error resolving intl.promise', err );
					} );
			} catch ( err ) {
				log( 'IntlInput', 'Error inside call_intl', err );
			}
		};

		// 5. Fetch IP Info if Country Unknown (First run / Expired cache)
		if ( ! country_code ) {
			country_code = 'us'; // Default fallback

			try {
				const controller = new AbortController();
				const timeoutId = setTimeout( () => controller.abort(), 2000 );

				fetch( 'https://ipinfo.io/json', {
					signal: controller.signal,
					mode: 'cors',
					credentials: 'omit',
				} )
					.then( response => {
						if ( ! response.ok ) {
							return Promise.reject( 'HTTP error' );
						}
						return response.json();
					} )
					.then( resp => {
						// Validate country code format (2 letter ISO code)
						if ( resp && resp.country && /^[A-Z]{2}$/i.test( resp.country ) ) {
							country_code = resp.country;
						} else {
							country_code = 'us';
						}
					} )
					.catch( ( err ) => {
						// Surface silent country-detection failures to dev/QA via the browser console; production users never see this.
						console.warn( '[ht_ctc] IntlInput: country detection failed, defaulting to US', err );
						country_code = 'us';
					} )
					.finally( () => {
						try {
							clearTimeout( timeoutId );
							setCtcStorageItem( 'country_code', country_code );
							setCtcStorageItem( 'country_code_date', country_code_date );
							add_prefer_countrys( country_code );
							call_intl();
						} catch { call_intl(); }
					} );
			} catch {
				country_code = 'us';
				call_intl();
			}
		} else {
			call_intl();
		}

		return intl;

	} catch ( error ) {
		log( 'IntlInput', 'intl_init global error', error );
		return null;
	}
};

const intl_onchange = ( context = document, currentApp ) => {
	try {
		if ( ! context || typeof context.querySelectorAll !== 'function' ) {
			return;
		}

		const intlInputs = context.querySelectorAll( '.intl_number' );

		intlInputs.forEach( ( input ) => {

			// // todo: maybe multiple event Listeners can be added here check once...

			// Mark as user-interacted on first real interaction
			[ 'focus', 'click', 'keydown' ].forEach( ( evt ) => {
				input.addEventListener( evt, function markInteracted () {
					// console.log(evt);
					this.dataset.userInteracted = 'true';
				}, { once: true } );
			} );

			[ 'input', 'countrychange' ].forEach( ( evtName ) => {
				input.addEventListener( evtName, function handleIntlChange ( event ) {
					try {
						if ( typeof intlTelInput === 'undefined' ) {
							return;
						}

						const changed = intlTelInput.getInstance( this );

						if ( ! changed ) {
							return;
						}

						const hiddenInput = changed.hiddenInput || this.closest( '.intl_tel_input_container' )
							.querySelector( 'input[type="hidden"]' );

						if ( hiddenInput ) {
							hiddenInput.value = changed.getNumber();

							if ( this.dataset.userInteracted ) {
								hiddenInput.dataset.changed = 'true';

								// Use Event Bus instead of global window leakage
								currentApp?.events?.emit( 'field:dirty', hiddenInput );
							}
						}

						// ctcNoTrack the visible elements in DOM
						// if ( this.dataset.userInteracted ) {
						// 	this.dataset.changed = 'true';
						// }

						// // Update demo object if present
						// if ( window.ht_ctc_admin_demo_var ) {
						// 	try {
						// 		window.ht_ctc_admin_demo_var.number = changed.getNumber();
						// 	} catch {
						// 		// ignore
						// 	}
						// }

						// // Fire custom event for valid number
						// try {
						// 	if ( changed.isValidNumber() ) {
						// 		const numberDetails = { number: changed.getNumber() };
						// 		document.dispatchEvent( new CustomEvent( 'ht_ctc_admin_event_valid_number', { detail: { data: numberDetails } } ) );
						// 	}
						// } catch {
						// 	// ignore validation error
						// }

					} catch {
						// Silently skip if something goes wrong in the event handler
						// console.warn('Ctc: intl event error', mainErr);
					}
				} );
			} );

			// Track country changes separately
			input.addEventListener( 'countrychange', function handleCountryChange ( event ) {
				// console.log(event);
				try {
					if ( typeof intlTelInput === 'undefined' ) {
						return;
					}
					const changed = intlTelInput.getInstance( this );
					if ( changed ) {
						const countryData = changed.getSelectedCountryData();
						if ( countryData && countryData.iso2 ) {
							add_prefer_countrys( countryData.iso2 );
						}
					}
				} catch {
					// ignore
				}
			} );
		} );
	} catch ( error ) {
		log( 'IntlInput', 'intl_onchange error', error );
	}
};

const add_prefer_countrys = ( country_code ) => {
	try {
		// Validate and sanitize country code
		if ( ! country_code || typeof country_code !== 'string' || ! /^[A-Z]{2}$/i.test( country_code ) ) {
			country_code = 'US';
		} else {
			country_code = country_code.toUpperCase();
		}

		let pre_countries = getCtcStorageItem( 'pre_countries' );

		if ( ! Array.isArray( pre_countries ) ) {
			pre_countries = [];
		}

		// Validate all existing country codes
		pre_countries = pre_countries.filter( code =>
			typeof code === 'string' && /^[A-Z]{2}$/i.test( code ) );

		pre_countries = pre_countries.filter( code => code !== country_code );
		pre_countries.unshift( country_code );

		if ( pre_countries.length > 3 ) {
			pre_countries = pre_countries.slice( 0, 3 );
		}

		setCtcStorageItem( 'pre_countries', pre_countries );
	} catch {
		// ignore storage error
	}
};
