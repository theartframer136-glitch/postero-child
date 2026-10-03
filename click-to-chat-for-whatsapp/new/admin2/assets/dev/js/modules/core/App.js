/**
 * Main App Orchestrator
 *
 * Central class that manages the application lifecycle.
 * - Initializes core systems (API, Storage, Events)
 * - Registers Managers (Settings, Repeater, etc.)
 * - Loads and transforms Settings Fields
 */
import Events from './Events.js';
import API from './API.js';
import * as Utils from './Utils.js';
import { log } from './Utils.js';
import * as Storage from './Storage.js';

// Logic modules can be imported here
// Feature-specific logic files.
// For example, Conditional.js handles showing/hiding fields based on user input.
import { initConditionalFieldLogic } from '../logic/Conditional.js';

// import { initSortable } from '../logic/Sortable.js';

export default class App {

	/**
	 * Setup the application.
	 * @param {Object} config - global javascript variables from PHP
	 */
	constructor ( config ) {
		this.config = config;

		// Initialize Core Helpers
		this.api = new API( config ); // AJAX wrapper

		this._events = new Events(); // Pub/Sub system

		this.utils = { ...Utils, initConditionalFieldLogic };

		// like localstorage, ..
		this.storage = Storage;

		// Prepare specific registries
		this.renderers = {};
		this.managers = {};

		/*
		 * Cache-key suffix for the per-tab field definitions in localStorage:
		 * plugin version + PRO version + admin locale. Any of these changing
		 * must invalidate cached fields — fields carry translated strings and
		 * PRO-dependent markup. Single source of truth for getFieldsForGroup()
		 * and clearOldCaches().
		 */
		const proSuffix = config.pro_version ? `_pro${config.pro_version}` : '';

		// en_US (the WP default) gets no suffix — keeps keys short for the common case.
		const localeSuffix = config.locale && 'en_US' !== config.locale ? `_${config.locale}` : '';
		this.fieldsCacheSuffix = `_${config.version}${proSuffix}${localeSuffix}`;

		this.clearOldCaches();
	}

	/**
	 * Garbage collection for old version caches in localStorage to prevent buildup.
	 */
	clearOldCaches () {
		try {
			const currentPrefix = 'ht_ctc_fields_';
			const expectedSuffix = this.fieldsCacheSuffix;

			const keysToRemove = [];

			for ( let i = 0; i < localStorage.length; i++ ) {
				const key = localStorage.key( i );
				if ( key && key.startsWith( currentPrefix ) ) {
					// If the key doesn't end with the exact version combo, it's stale.
					// This handles main updates, pro updates, and pro activation/deactivation.
					if ( ! key.endsWith( expectedSuffix ) ) {
						keysToRemove.push( key );
					}
				}
			}

			keysToRemove.forEach( staleKey => localStorage.removeItem( staleKey ) );
		} catch ( error ) {
			log( 'App', 'Error clearing old caches', error );
		}
	}

	/**
	 * Invalidate cached fields for one or more settings groups.
	 *
	 * Field HTML is cached in localStorage (`ht_ctc_fields_<group>_<version>[_pro<version>]`)
	 * and, when preloaded by PHP, as a `window.ht_ctc_fields_<group>` global. Those caches
	 * normally only refresh on a version change (see `clearOldCaches`). When an action changes
	 * a tab's server-rendered HTML within the same version — e.g. Pro license activate/deactivate
	 * flips the active/inactive markup — call this so the next load re-fetches fresh fields.
	 *
	 * @param {string|string[]} groups - Group id(s), e.g. 'license_settings'.
	 */
	clearFieldsCache ( groups ) {
		const list = Array.isArray( groups ) ? groups : [ groups ];

		list.forEach( group => {
			if ( ! group || typeof group !== 'string' ) { return; }

			try {
				const prefix = `ht_ctc_fields_${group}`;

				// Drop every version-suffixed localStorage entry for this group.
				for ( let i = localStorage.length - 1; i >= 0; i-- ) {
					const key = localStorage.key( i );
					if ( key && key.startsWith( prefix ) ) {
						localStorage.removeItem( key );
					}
				}

				// Drop the PHP-preloaded window copy so an in-session re-render can't reuse it.
				Utils.setSafeProperty( window, prefix, undefined );
			} catch ( error ) {
				log( 'App', `Error clearing fields cache for ${group}`, error );
			}
		} );
	}

	/**
	 * Register a Manager (feature module).
	 * Managers handle logic for specific areas like Settings, Themes, etc.
	 *
	 * @param {string} name
	 * @param {Object|Class} ManagerClass
	 */
	// 1. Static Classes: initialized immediately via `init(app)`
	// 2. Instantiable Classes: `new Manager(app)` -> `init()`
	registerManager ( name, ManagerClass ) {
		// Initialize the manager and store it
		log( 'App', `Registering manager: ${name}` );
		if ( typeof ManagerClass.init === 'function' ) {
			// Static object pattern:
			// If the manager has a static init() method, we call it directly.
			// This is useful for simple managers that don't need to maintain instance state.
			const isSuccess = this.utils.safeRun( () => ManagerClass.init( this ), name );

			if ( isSuccess ) {
				// this.managers[ name ] = ManagerClass;
				Utils.setSafeProperty( this.managers, name, ManagerClass );
			} else {
				return; // Abort registration if init fails
			}
		} else {
			// Class pattern:
			// If the manager needs to be instantiated (using 'new'), we create an instance
			// and then call its init() method.

			let managerInstance;
			try {
				managerInstance = new ManagerClass( this );
			} catch ( error ) {
				console.error( `CTC: Manager instantiation failed for ${name}:`, error );
				return; // Abort registration
			}

			if ( typeof managerInstance.init === 'function' ) {
				const isSuccess = this.utils.safeRun( () => managerInstance.init(), name );
				if ( ! isSuccess ) {
					return; // Abort registration if init fails
				}
			}

			// this.managers[ name ] = new ManagerClass( this );
			Utils.setSafeProperty( this.managers, name, managerInstance );
		}

		// Dispatch custom event for external plugins (like Pro) to hook into lazy-loaded managers
		document.dispatchEvent( new CustomEvent( `ctc_manager_registered_${name}`, {
			// detail: { manager: this.managers[ name ], app: this },
			detail: { manager: Utils.getSafeProperty( this.managers, name ), app: this },
		} ) );
	}

	/**
	 * Register a Field Renderer.
	 * Maps a field type (e.g. 'text') to a function that creates its HTML.
	 */
	registerRenderer ( type, rendererFn ) {
		// Store renderer
		// this.renderers[ type ] = rendererFn;
		Utils.setSafeProperty( this.renderers, type, rendererFn );
	}

	/**
	 * Create HTML for a specific field.
	 * Uses the registered renderers.
	 *
	 * e.g. field = todo
	 */
	createFieldElement ( field, context = document ) {
		// 1. Get renderer by field.field_type
		// 2. Call renderer(field) to generate the HTML
		// 3. Return the element (or an error placeholder if the renderer is missing)
		const fieldType = field.field_type;

		// const renderer = this.renderers[ fieldType ];
		const renderer = Utils.getSafeProperty( this.renderers, fieldType );

		if ( typeof renderer === 'function' ) {
			try {
				return renderer( field, context );
			} catch ( error ) {
				console.error( `CtC: Error rendering field ${fieldType}:`, error );
				const errorDiv = document.createElement( 'div' );
				errorDiv.className = 'ctc-field-error';
				// eslint-disable-next-line no-unsanitized/property -- Static HTML wrapper; dynamic values are safely escaped via Utils.escapeHTML
				errorDiv.innerHTML = '<strong>Error:</strong> Failed to render ' +
					`<code>${Utils.escapeHTML( String( fieldType ) )}</code>. ` +
					`<small>${Utils.escapeHTML( String( error.message ) )}</small>`;
				return errorDiv;
			}
		}

		console.warn( `CtC: No renderer for: ${fieldType}` );
		const errorDiv = document.createElement( 'div' );
		errorDiv.className = 'ctc-field-error';
		// eslint-disable-next-line no-unsanitized/property -- Static HTML wrapper; dynamic values are safely escaped via Utils.escapeHTML
		errorDiv.innerHTML = '<strong>Error:</strong> Field type ' +
			`<code>${Utils.escapeHTML( String( fieldType ) )}</code> N/A.`;
		return errorDiv;
	}

	/**
	 * Retrieve field schema for a given settings group — orchestrates cache → network.
	 *
	 * Cache tiers (in order):
	 *   1. Window global (preloaded by PHP — fastest)
	 *   2. LocalStorage (SPA-cached — fast)
	 *   3. REST API (network fallback — slowest)
	 */
	async getFieldsForGroup ( group ) {
		const cacheKey = `ht_ctc_fields_${group}${this.fieldsCacheSuffix}`;
		const windowKey = `ht_ctc_fields_${group}`;

		const cached = this.getCachedFields( cacheKey, windowKey );
		if ( cached ) { return cached; }

		return this.fetchFieldsFromAPI( group, cacheKey );
	}

	/**
	 * Look up cached fields from window global or LocalStorage.
	 * Self-heals on corrupted LocalStorage JSON.
	 *
	 * @param {string} cacheKey  - LocalStorage key.
	 * @param {string} windowKey - Property name to read off `window`.
	 * @returns {Array|Object|null} Cached fields, or null on miss.
	 */
	getCachedFields ( cacheKey, windowKey ) {
		const preloaded = Utils.getSafeProperty( window, windowKey );
		if ( preloaded ) { return preloaded; }

		const cached = this.storage.getItem( cacheKey, false );
		if ( ! cached ) { return null; }

		try {
			return JSON.parse( cached );
		} catch {
			// Corrupted/invalid JSON — drop the entry so we don't keep retrying it.
			localStorage.removeItem( cacheKey );
			return null;
		}
	}

	/**
	 * Fetch fields from REST API; caches the response on success.
	 *
	 * @param {string} group    - Settings group identifier.
	 * @param {string} cacheKey - LocalStorage key to write the response under.
	 * @returns {Promise<Array|Object>}
	 * @throws {Error} If the API response indicates failure.
	 */
	async fetchFieldsFromAPI ( group, cacheKey ) {
		const apiGroup = group.replace( /_/g, '-' );
		const url = `${this.api.endpoints.GET_FIELDS}?group=${apiGroup}&v=${this.config.version}`;
		const result = await this.api.request( url );

		if ( result.success && result.fields ) {
			this.storage.setItem( cacheKey, JSON.stringify( result.fields ), false );
			return result.fields;
		}

		throw new Error( result.message || 'Failed to retrieve settings from server.' );
	}

	/**
	 * Load settings for a tab.
	 * 1. Retrieve Fields (Cache or API)
	 * 2. Invoke Renderers
	 */
	async loadTabSettings ( tabId, containerClass = '' ) {
		// Logic to fetch fields JSON and call renderTabFields
		const panel = document.getElementById( tabId );

		// Prevent redundant loads:
		// - If panel is missing, abort.
		// - If already loaded, abort.
		// - If currently loading, abort.
		if ( ! panel || panel.dataset.loaded === 'true' || panel.dataset.loading === 'true' ) { return; }

		// Set loading lock
		panel.dataset.loading = 'true';

		const targetClass = containerClass || 'fields-container';

		const container = Array.from( panel.children )
			.find( child => child.classList.contains( targetClass ) );

		if ( ! container ) {
			// panel.dataset.loading = 'false'; // Release lock so retry is possible
			return;
		}

		// Determine the settings group (e.g., 'general_settings').
		// This is used for caching and API requests.
		const group = ( panel.getAttribute( 'data-group' ) || tabId ).replace( /-/g, '_' );

		try {
			// Fetch cached or network fields via unified helper
			const fields = await this.getFieldsForGroup( group );

			// if fields, then render
			if ( fields ) {

				// Dynamic Module Loading (Phase 1: Fetch and Register System Modules)
				// Load any modules defined by PHP that are assigned to this tab concurrently.
				if ( this.config.modulesPath ) {
					// 1. Filter out modules that apply to the current tabId.
					const modulesToLoad = Object.entries( this.config.modulesPath )
						.filter( ( [ , moduleConf ] ) =>
							moduleConf.tabs && moduleConf.tabs.includes( tabId ) );

					if ( modulesToLoad.length > 0 ) {
						// 2. Fetch + register them all concurrently (shared loader).
						await Promise.allSettled( modulesToLoad.map( ( [ key, moduleConf ] ) =>
							this.loadModule( key, moduleConf ) ) );
					}
				}

				// RENDER: Convert JSON configuration -> HTML Elements
				// Ensure renderers registered above are now used explicitly
				await this.renderTabFields( fields, container );
				panel.dataset.loaded = 'true';

				// Post-render initialization:
				// 1. Setup conditional logic (show/hide fields based on values - e.g., using 'data-watch' attributes).
				initConditionalFieldLogic( document );

				// Dynamic Module Loading (Phase 2: Execute Post-Render Initialization Methods)
				if ( this.config.modulesPath ) {
					Object.entries( this.config.modulesPath )
						.filter( ( [ , moduleConf ] ) =>
							moduleConf.tabs &&
							moduleConf.tabs.includes( tabId ) &&
							moduleConf.method )
						.forEach( ( [ key, moduleConf ] ) =>
							this.runModuleMethod(
								moduleConf._loadedModule,
								moduleConf,
								panel,
								key,
							) );
				}
			}
		} catch ( error ) {
			console.error( `Error loading ${tabId}:`, error );

			// Translate technical JS/Server errors into "Human-Friendly" hints
			// (shared mapping — see Utils.friendlyErrorMessage), while still showing
			// the raw technical detail below for developers.
			const friendlyMessage = Utils.friendlyErrorMessage( error );

			const errorWrapper = document.createElement( 'div' );
			errorWrapper.className = 'ctc-error-container';
			errorWrapper.style.padding = '20px';
			errorWrapper.style.textAlign = 'center';

			// eslint-disable-next-line no-unsanitized/property -- Static HTML; dynamic error details are safely escaped via Utils.escapeHTML
			errorWrapper.innerHTML = `
				<div class="ctc-error-message" style="margin-bottom: 15px; color: #d63638;">
					<p style="margin: 0; font-weight: 600;">Error loading settings</p>
					<div style="margin-top: 5px; line-height: 1.4;">
						<small style="opacity: 0.8; display: block; margin-bottom: 4px;">Technical Detail: ${Utils.escapeHTML( String( error.message || 'N/A' ) )}</small>
						<span style="font-size: 13px;">${Utils.escapeHTML( friendlyMessage )}</span>
					</div>
				</div>
			`;

			const retryBtn = document.createElement( 'button' );
			retryBtn.className = 'button button-secondary';

			if ( friendlyMessage.includes( 'Session expired' ) ) {
				retryBtn.innerText = 'Refresh Page';
				retryBtn.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					window.location.reload();
				}, { once: true } );
			} else {
				retryBtn.innerText = 'Retry';
				retryBtn.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					this.loadTabSettings( tabId, containerClass );
				}, { once: true } );
			}

			errorWrapper.appendChild( retryBtn );
			container.innerHTML = ''; // Clear loading spinner
			container.appendChild( errorWrapper );
		} finally {
			// Release loading lock
			panel.dataset.loading = 'false';
		}

	}

	/**
	 * Render the list of fields into the container sequentially to improve rendering performance.
	 */
	renderTabFields ( fields, container ) {
		return new Promise( ( resolve ) => {
			// Clear existing content to avoid duplicates or stale data
			container.innerHTML = '';

			// Normalize fields structure:
			// - Logic supports both Array (simple list) and Object (grouped fields).
			// - If Object: values are flattened into a single array to simplify rendering.
			let fieldsToRender = [];
			if ( Array.isArray( fields ) ) {
				fieldsToRender = fields;
			} else if ( fields && typeof fields === 'object' ) {
				fieldsToRender = Object.values( fields )
					.reduce( ( acc, val ) => acc.concat( val ), [] );
			}

			const totalFields = fieldsToRender.length;
			if ( totalFields === 0 ) {
				resolve();
				return;
			}

			// Optimization: Batch DOM Updates using requestAnimationFrame
			// This prevents long tasks from blocking the main thread when rendering many fields.
			const chunkSize = 20;
			let index = 0;

			const renderChunk = () => {
				const fragment = document.createDocumentFragment();
				const max = Math.min( index + chunkSize, totalFields );

				for ( ; index < max; index++ ) {
					/** In JavaScript, arrays are actually special types of objects.
					 * The elements are stored as key-value pairs where keys are strings.
					 * Example array
					 * const arr = ['a', 'b', 'c'];

					 * Internally, it behaves like an object:
					 * {
					 *   "0": "a",   // index 0 stored as string key "0"
					 *   "1": "b",   // index 1 stored as string key "1"
					 *   "2": "c",   // index 2 stored as string key "2"
					 *   length: 3   // special property tracking number of elements
					 * }
					 * Accessing array elements:
					 * arr[0];    // same as arr["0"]
					 * arr[1];    // same as arr["1"]
					 * This is why functions that work with object keys
					 * (like hasOwnProperty) also work with arrays.
					*/
					// const field = fieldsToRender[ index ];
					const field = Utils.getSafeProperty( fieldsToRender, String( index ) );

					// Transform the field config object into a DOM element
					const el = this.createFieldElement( field );

					// Append result to the fragment
					if ( el ) { fragment.appendChild( el ); }
				}

				container.appendChild( fragment );

				if ( index < totalFields ) {
					requestAnimationFrame( renderChunk );
				} else {
					resolve();
				}
			};

			requestAnimationFrame( renderChunk );
		} );
	}

	// Public method to load and init IntlInput on demand
	async loadAndInitIntlInput ( containerClass = 'intl_number', context = document ) {
		const intlConf = this.config.modulesPath?.intlInput;
		if ( intlConf && intlConf.path ) {
			try {
				const module = await Utils.importWithRetry( () =>
					// eslint-disable-next-line no-unsanitized/method -- Path is from trusted plugin configuration localized by PHP
					import( /* webpackIgnore: true */ intlConf.path ) );
				if ( module && typeof module.initIntlInput === 'function' ) {
					module.initIntlInput( containerClass, context, this );
				}
			} catch ( error ) {
				console.warn( 'CtC: Error loading IntlInput module dynamically', error );
			}
		}
	}

	/**
	 * Dynamic-import a single modulesPath entry and register what it exposes.
	 *
	 * `modulesPath` is declared in PHP (class-ht-ctc-admin-page-scripts.php),
	 * which documents the per-entry keys and the three load triggers:
	 *   • tabs  → loadTabSettings()      (on tab open)
	 *   • delay → loadDelayedModules()   (once, after boot)
	 *   • by key → e.g. loadAndInitIntlInput() (explicit on-demand)
	 * This method is the shared loader all three routes funnel through.
	 *
	 * Single source of truth for "load a module declared in PHP". Errors are
	 * logged, never thrown.
	 *
	 * Registers:
	 *   - managerId:  module.default as a Manager (new + init()).
	 *   - rendererId: module.default as a field renderer (field, context, config).
	 * Caches the module on the config (`_loadedModule`) for a later
	 * runModuleMethod() call.
	 *
	 * @param {string} key        Module key (for error logs).
	 * @param {Object} moduleConf Module config from config.modulesPath.
	 * @returns {Promise<Object|null>} The imported module, or null on failure.
	 */
	async loadModule ( key, moduleConf ) {
		try {
			const module = await Utils.importWithRetry( () =>
				// eslint-disable-next-line no-unsanitized/method -- Path is from trusted plugin configuration localized by PHP
				import( /* webpackIgnore: true */ moduleConf.path ) );

			// Cache for a later post-render / post-load method init.
			moduleConf._loadedModule = module;

			// a. Manager class (e.g. RepeaterManager, PreviewManager).
			if ( moduleConf.managerId && module.default ) {
				this.registerManager( moduleConf.managerId, module.default );
			}

			// b. Renderer fn receiving (field, context, config).
			if ( moduleConf.rendererId && typeof module.default === 'function' ) {
				this.registerRenderer(
					moduleConf.rendererId,
					( field, context ) => module.default( field, context, this.config ),
				);
			}

			return module;
		} catch ( error ) {
			console.error( `CtC: Error loading dynamic module: ${key}`, error );
			return null;
		}
	}

	/**
	 * Invoke a module's post-load init method, if it declares one.
	 *
	 * @param {Object|null} moduleObj  Imported module (or null — no-op).
	 * @param {Object}      moduleConf Module config (method, arg).
	 * @param {*}           context    2nd arg + fallback 1st arg (tab panel | null).
	 * @param {string}      key        Module key (for error logs).
	 */
	runModuleMethod ( moduleObj, moduleConf, context, key ) {
		if ( ! moduleObj || ! moduleConf.method ) { return; }
		try {
			const methodFn = Utils.getSafeProperty( moduleObj, moduleConf.method );
			if ( typeof methodFn === 'function' ) {
				methodFn( moduleConf.arg || context, context, this );
			}
		} catch ( error ) {
			console.error( `CtC: Error initializing dynamic module method: ${key}`, error );
		}
	}

	/**
	 * Load "delayed" modules — entries in config.modulesPath that carry a
	 * `delay` (ms) and are NOT tied to a tab. Used for global features (e.g.
	 * the live Preview) we want kept out of the initial bundle but loaded
	 * automatically a short time after boot.
	 *
	 * Reuses loadModule()/runModuleMethod(), firing once per module via
	 * setTimeout instead of on tab activation.
	 */
	loadDelayedModules () {
		if ( ! this.config.modulesPath ) { return; }

		Object.entries( this.config.modulesPath )
			.filter( ( [ , moduleConf ] ) => moduleConf && moduleConf.delay && moduleConf.path )
			.forEach( ( [ key, moduleConf ] ) => {
				setTimeout( async () => {
					const module = await this.loadModule( key, moduleConf );
					this.runModuleMethod( module, moduleConf, null, key );
				}, moduleConf.delay );
			} );
	}

	// Getters for core systems
	get events () { return this._events; }

	// get api instance
	getApi () { return this.api; }

	/**
	 * Pre-fetches settings for all inactive tabs in the background.
	 * Ensures 0ms latency when switching to new tabs on slow connections.
	 */
	async preloadBackgroundTabs () {
		const panels = document.querySelectorAll( '.settings-panel' );

		for ( const panel of panels ) {
			const group = ( panel.getAttribute( 'data-group' ) || panel.id ).replace( /-/g, '_' );
			if ( ! group || group === 'general_settings' ) { continue; } // general is already loaded

			try {
				// We don't need to do checking here; the helper method handles cache verification automatically
				// eslint-disable-next-line no-await-in-loop -- Sequential preloading is intentional to avoid network congestion
				await this.getFieldsForGroup( group );
			} catch ( error ) {
				// Silently fail prefetching so it naturally falls back to normal fetch on user click
				log( 'App', `Preload failed for ${group}`, error );
			}

			// Yield small delay to avoid browser congestion on 3G devices
			// eslint-disable-next-line no-await-in-loop -- Intentional throttling between preloads
			await new Promise( resolve => setTimeout( resolve, 500 ) );
		}
	}
}
