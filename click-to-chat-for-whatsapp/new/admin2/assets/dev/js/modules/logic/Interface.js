/**
 * Interface Logic
 * Manages the "Application Shell" - sidebar, navigation, global frame interactions.
 *
 * Flow Overview:
 * 1. Initial Load: `initNavigation` checks URL for `tab` param or `#hash`.
 * 2. Tab Activation: `activateTab` updates UI, saves state, and triggers `App.loadTabSettings` (Lazy Load).
 * 3. Deep Linking: If a section ID is present (e.g. #tab/section), `scrollToElement` handles the jump.
 * 4. Dynamic Handling: Since fields load via AJAX, `scrollToElement` uses a retry mechanism to "wait" for elements.
 */
import { getCtcStorageItem, setCtcStorageItem } from '../core/Storage.js';
import { log } from '../core/Utils.js';

export default class Interface {
	static init ( app ) {
		log( 'Interface', 'Initializing...' );
		this.app = app;

		document.addEventListener( 'DOMContentLoaded', () => {
			this.initSidebar();
			this.initNavigation();
			this.initTabs();

			this.app.utils.initConditionalFieldLogic( document );

			this.initHelpIcons();
			this.initGreetingsImage();
			this.initRightSidebar();
		} );
	}

	/**
	 * Sidebar & Toggle Menu Logic
	 */
	static initSidebar () {
		const menuToggle = document.getElementById( 'menu-toggle' );
		const closeSidebar = document.getElementById( 'close-sidebar' );
		const sidebar = document.getElementById( 'sidebar' );
		const settingsToggle = document.getElementById( 'settings-toggle' );
		const settingsDropdown = document.getElementById( 'settings-dropdown' );

		this.desktopQuery = window.matchMedia( '(min-width: 768px)' );

		if ( this.desktopQuery.matches && sidebar ) {
			sidebar.classList.add( 'expanded' );
		}

		if ( menuToggle && sidebar ) {
			menuToggle.addEventListener( 'click', () => {
				if ( this.desktopQuery.matches ) {
					sidebar.classList.toggle( 'expanded' );
				} else {
					sidebar.classList.toggle( 'open' );
					document.body.style.overflow = sidebar.classList.contains( 'open' ) ? 'hidden' : '';
				}
			} );
		}

		if ( closeSidebar && sidebar ) {
			closeSidebar.addEventListener( 'click', () => {
				sidebar.classList.remove( 'open' );
				document.body.style.overflow = '';
			} );
		}

		document.addEventListener( 'click', ( event ) => {
			if (
				! this.desktopQuery.matches &&
				sidebar &&
				menuToggle &&
				! sidebar.contains( event.target ) &&
				! menuToggle.contains( event.target ) &&
				sidebar.classList.contains( 'open' )
			) {
				sidebar.classList.remove( 'open' );
				document.body.style.overflow = '';
			}
		} );

		if ( settingsToggle && settingsDropdown ) {
			settingsToggle.addEventListener( 'click', ( event ) => {
				event.stopPropagation();
				settingsDropdown.classList.toggle( 'hidden' );
			} );
			document.addEventListener( 'click', ( event ) => {
				if (
					! settingsDropdown.contains( event.target ) &&
					! settingsToggle.contains( event.target )
				) {
					settingsDropdown.classList.add( 'hidden' );
				}
			} );
		}
	}

	/**
	 * Main Navigation & Deep Link Handling
	 */
	static initNavigation () {
		log( 'Interface', 'Initializing Navigation...' );
		const navItems = document.querySelectorAll( '.nav-item' );

		// 1. Determine Initial Active Tab
		// Order of priority: URL Parameter (?tab=) > URL Hash (#tab) > LocalStorage > Default (General)
		const urlParams = new URLSearchParams( window.location.search );
		const urlTabId = urlParams.get( 'tab' );
		const hashTabId = window.location.hash.replace( '#', '' )
			.split( '/' )[ 0 ];

		const activeTabId = urlTabId || hashTabId || getCtcStorageItem( 'active-tab' );

		// 2. Initial Activation
		const activeTab = activeTabId ?
			document.querySelector( `.nav-item[data-tab="${activeTabId}"]` ) :
			null;
		const activePanel = activeTabId ? document.getElementById( activeTabId ) : null;

		if ( activeTab && activePanel ) {
			const initialSectionId = window.location.hash.replace( '#', '' )
				.split( '/' )[ 1 ];

			// If we have a section ID (deep link), we pass `skipScroll=true` to avoid jumping to top
			this.activateTab( activeTabId, !! initialSectionId )
				.then( () => {
					if ( initialSectionId ) {
						// Wait for content to render, then jump to section
						this.scrollToElement( initialSectionId );
					}
				} );
		} else {
			const defaultActive = document.querySelector( '.nav-item.active' );
			if ( defaultActive ) {
				this.activateTab( defaultActive.getAttribute( 'data-tab' ) );
			}
		}

		Interface.updateMobileSectionLabel();

		// 3. Tab Click Listeners
		navItems.forEach( ( item ) => {
			item.addEventListener( 'click', () => {
				this.activateTab( item.getAttribute( 'data-tab' ) );
			} );
		} );

		// 4. Logo / Home Click Logic
		const logoHome = document.getElementById( 'logo-home' );
		if ( logoHome ) {
			logoHome.addEventListener( 'click', () => {
				const generalTab = document.querySelector( '.nav-item[data-tab="general-settings"]' );
				if ( generalTab ) { generalTab.click(); }
			} );
		}

		// 5. Dynamic Module Scroll Synchronization
		// Some sections are created ONLY when they enter the viewport (Lazy Sections)
		document.addEventListener( 'ht_ctc_register_section_dynamic', ( event ) => {
			const { element, id } = event.detail;
			if ( element && id && 'IntersectionObserver' in window ) {
				const observer = new IntersectionObserver( ( entries, obs ) => {
					entries.forEach( entry => {
						if ( entry.isIntersecting ) {
							this.app.loadTabSettings( id );
							obs.unobserve( entry.target );
						}
					} );
				}, { rootMargin: '200px' } );
				observer.observe( element );
			} else if ( id ) {
				this.app.loadTabSettings( id );
			}
		} );

		// 6. Global Link Interceptor (Deep Links)
		// Internal clicks on <a href="#tab-id/section-id">
		document.addEventListener( 'click', async ( event ) => {
			const link = event.target.closest( 'a[href^="#"]' );
			if ( ! link ) { return; }

			const fullHash = link.getAttribute( 'href' )
				.replace( '#', '' );
			if ( ! fullHash ) { return; }

			const [ tabId, sectionId ] = fullHash.split( '/' );
			const navItem = document.querySelector( `.nav-item[data-tab="${tabId}"]` );

			if ( navItem ) {
				event.preventDefault();

				// Switch tab (and prevent default scroll-to-top if we are targeting a sub-section)
				await this.activateTab( tabId, !! sectionId );
				if ( sectionId ) {
					this.scrollToElement( sectionId );
				}
			}
		} );

		// 7. Drill-Down Menu Logic (Sidebar Submenus)
		document.addEventListener( 'click', ( event ) => {
			const drillDownBtn = event.target.closest( '.drill-down-btn' );
			const backBtn = event.target.closest( '.drill-down-back-btn' );

			if ( drillDownBtn ) {
				const targetMenuId = drillDownBtn.getAttribute( 'data-target' );
				const targetMenu = document.getElementById( targetMenuId );
				if ( targetMenu ) {
					document.querySelectorAll( '.sidebar-menu' )
						.forEach( menu => menu.classList.remove( 'active' ) );
					targetMenu.classList.add( 'active' );
					( targetMenu.querySelector( '.nav-item.active' ) || targetMenu.querySelector( '.nav-item' ) )?.click();
				}
			}

			if ( backBtn ) {
				const targetMenuId = backBtn.getAttribute( 'data-target' );
				const targetMenu = document.getElementById( targetMenuId );
				if ( targetMenu ) {
					document.querySelectorAll( '.sidebar-menu' )
						.forEach( menu => menu.classList.remove( 'active' ) );
					targetMenu.classList.add( 'active' );
					( targetMenu.querySelector( '.nav-item.active' ) || targetMenu.querySelector( '.nav-item' ) )?.click();
				}
			}
		} );
	}

	/**
	 * Activates a settings tab.
	 *
	 * Flow:
	 * 1. UI Switch (CSS classes)
	 * 2. Persistence (LocalStorage)
	 * 3. Scroll Management (Reset to top or stay put)
	 * 4. Data Loading (Fetch fields via API if not loaded)
	 *
	 * @param {string} tabId - The ID of the tab to activate.
	 * @param {boolean} skipScroll - If true, keeps the current scroll position (used for deep linking).
	 */
	static async activateTab ( tabId, skipScroll = false ) {
		const navItem = document.querySelector( `.nav-item[data-tab="${tabId}"]` );
		const panel = document.getElementById( tabId );
		if ( ! navItem || ! panel ) { return; }

		// UI/State Updates
		document.querySelectorAll( '.nav-item' )
			.forEach( nav => nav.classList.remove( 'active' ) );
		document.querySelectorAll( '.settings-panel' )
			.forEach( panel => panel.classList.remove( 'active' ) );

		navItem.classList.add( 'active' );
		panel.classList.add( 'active' );
		setCtcStorageItem( 'active-tab', tabId );

		// Drill-down menu sync
		const parentMenu = navItem.closest( '.sidebar-menu' );
		if ( parentMenu ) {
			document.querySelectorAll( '.sidebar-menu' )
				.forEach( menu => menu.classList.remove( 'active' ) );
			parentMenu.classList.add( 'active' );
		}

		// Scroll management
		if ( ! skipScroll ) {
			Interface.resetScrollTo();
		}

		// Lazy Load content
		if ( panel.dataset.loaded === 'false' ) {
			await Interface.app.loadTabSettings( tabId );
		}

		Interface.updateMobileSectionLabel();
		Interface.updateProWidget( tabId );

		Interface.app.events?.emit( 'tab:changed', tabId );

		// Mobile behavior: close sidebar after selection
		const sidebar = document.getElementById( 'sidebar' );
		if ( ! Interface.desktopQuery.matches && sidebar ) {
			sidebar.classList.remove( 'open' );
			document.body.style.overflow = '';
		}
	}

	/**
	 * Smoothly scrolls to a specific element within the main container.
	 *
	 * Why the Retry Mechanism?
	 * Since fields are loaded as JSON and rendered dynamically, the target element
	 * might not exist in the DOM immediately when a link is clicked.
	 * We retry for up to 10 seconds to wait for the AJAX request and rendering to complete.
	 *
	 * @param {string} elementId - The ID of the target element (Field ID, Card ID, etc.)
	 */
	static scrollToElement ( elementId, attempts = 0 ) {
		const container = document.querySelector( '.main-content' );
		let element = document.getElementById( elementId );

		// Retry if element not found in DOM
		// 50 attempts * 200ms = 10s max wait
		if ( ! element && attempts < 50 ) {
			setTimeout( () => this.scrollToElement( elementId, attempts + 1 ), 200 );
			return;
		}

		if ( ! element ) {
			log( 'Interface', `scrollToElement: element #${elementId} not found after max retries.` );
			return;
		}

		if ( element && container ) {
			// Ensure element has dimensions (wait for layout calculation)
			const rect = element.getBoundingClientRect();
			if ( ( rect.width === 0 || rect.height === 0 ) && attempts < 50 ) {
				setTimeout( () => this.scrollToElement( elementId, attempts + 1 ), 200 );
				return;
			}

			// Ensure the panel containing the element is visible
			const panel = element.closest( '.settings-panel' );
			if ( panel && ! panel.classList.contains( 'active' ) ) {
				setTimeout( () => this.scrollToElement( elementId, attempts + 1 ), 100 );
				return;
			}

			// Better visibility: Scroll to the wrapper group for better context
			const wrapper = element.closest( '.form-group' ) || element.closest( '.ctc-card' ) || element.closest( '.field-group' );
			if ( wrapper ) { element = wrapper; }

			const containerRect = container.getBoundingClientRect();
			const elementRect = element.getBoundingClientRect();
			const relativeTop = elementRect.top - containerRect.top + container.scrollTop;
			const targetScroll = Math.max( 0, relativeTop - 20 );

			// Smooth scroll to calculated position
			if ( Math.abs( container.scrollTop - targetScroll ) > 5 ) {
				container.scrollTo( {
					top: targetScroll,
					behavior: 'smooth',
				} );
			}

			// Highlight the target visually for a few seconds
			element.classList.add( 'ctc-highlight-jump' );
			setTimeout( () => element.classList.remove( 'ctc-highlight-jump' ), 8000 );
		}
	}

	/**
	 * Inner Page Tabs Delegation (e.g. Settings within a Card)
	 */
	static initTabs () {
		document.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '.tab-button' );
			if ( ! button || button.classList.contains( 'nav-item' ) ) { return; }

			const tabContainer = button.closest( '.tabs' );
			if ( ! tabContainer ) { return; }

			event.preventDefault();
			const tabToShow = button.getAttribute( 'data-tab' );

			tabContainer.querySelectorAll( '.tab-button' )
				.forEach( btn => {
					btn.classList.remove( 'active' );
					btn.setAttribute( 'aria-selected', 'false' );
				} );
			button.classList.add( 'active' );
			button.setAttribute( 'aria-selected', 'true' );

			tabContainer.querySelectorAll( '.tab-content' )
				.forEach( content => content.classList.remove( 'active' ) );

			const targetContent = document.getElementById( `${tabToShow}-tab` );
			if ( targetContent ) { targetContent.classList.add( 'active' ); }

			Interface.app.events?.emit( 'tab:changed', tabToShow );
		} );
	}

	/**
	 * Adaptive Help Icons Logic
	 */
	static initHelpIcons () {
		document.addEventListener( 'click', ( event ) => {
			const toggle = event.target.closest( '.help-toggle' );
			if ( ! toggle ) { return; }

			event.preventDefault();
			const parent = toggle.closest( '.form-group' );
			if ( parent ) {
				parent.classList.toggle( 'help-active' );
			}
		} );
	}

	/**
	 * WordPress Media Uploader integration
	 *
	 * @todo Refactor and move this event binding directly inside BlockUploadImage.js to make the component self-contained.
	 */
	static initGreetingsImage () {
		let mediaUploader;
		document.addEventListener( 'click', ( event ) => {
			const addBtn = event.target.closest( '.ctc_add_image_wp' );
			const removeBtn = event.target.closest( '.ctc_remove_image_wp' );

			if ( addBtn ) {
				event.preventDefault();
				if ( mediaUploader ) { mediaUploader.open(); return; }
				if ( ! window.wp?.media ) { return; }

				mediaUploader = wp.media.frames.file_frame = wp.media( {
					title: 'Select Header Image',
					button: { text: 'Select' },
					multiple: false,
				} );

				mediaUploader.on( 'select', () => {
					const attachment = mediaUploader.state()
						.get( 'selection' )
						.first()
						.toJSON();
					if ( ! attachment ) { return; }
					const wrapper = addBtn.closest( '.ctc-image-upload-wrapper' ) || document;
					const input = wrapper.querySelector( '.g_header_image' );
					const preview = wrapper.querySelector( '.g_header_image_preview' );
					const remBtn = wrapper.querySelector( '.ctc_remove_image_wp' );

					if ( input ) {
						input.value = attachment.url;
						input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
					if ( preview ) { preview.src = attachment.url; preview.style.display = 'block'; }
					if ( remBtn ) { remBtn.style.display = 'inline-block'; }
				} );
				mediaUploader.open();
			}

			if ( removeBtn ) {
				event.preventDefault();
				const wrapper = removeBtn.closest( '.ctc-image-upload-wrapper' ) || document;
				const input = wrapper.querySelector( '.g_header_image' );
				const preview = wrapper.querySelector( '.g_header_image_preview' );
				if ( input ) {
					input.value = '';
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
				if ( preview ) { preview.style.display = 'none'; }
				removeBtn.style.display = 'none';
			}
		} );
	}

	/**
	 * Resets scroll position to top
	 */
	static resetScrollTo () {
		const target = document.querySelector( '.main-content' );
		if ( target ) { target.scrollTo( 0, 0 ); }
		window.scrollTo( 0, 0 );
	}

	/**
	 * Updates the current section label (Mobile Top Bar)
	 */
	static updateMobileSectionLabel () {
		const label = document.getElementById( 'mobile-section-label' );
		const activeNav = document.querySelector( '.nav-item.active' );
		if ( ! label || ! activeNav ) { return; }

		const span = activeNav.querySelector( 'span:not(.dashicons):not(.ctc-icon)' );
		label.textContent = span ? span.textContent.trim() : activeNav.textContent.trim();
	}

	/**
	 * PRO widget (free version, right sidebar): show the feature items
	 * relevant to the active tab. Each <li data-tabs="..."> lists the nav-tab
	 * ids it belongs to; when none match the active tab, the items tagged
	 * `default` are shown instead. Widget markup exists only without PRO.
	 *
	 * On the 'pro-features' tab the whole widget is hidden — the full PRO
	 * features page is already on screen, so the sidebar teaser is redundant.
	 *
	 * @param {string} tabId - The activated nav tab id (e.g. 'greetings-settings').
	 */
	static updateProWidget ( tabId ) {
		const promoWidget = document.querySelector( '.ctc-pro-promo' );
		if ( ! promoWidget ) { return; }

		// Redundant on the PRO features page itself; show it everywhere else.
		promoWidget.hidden = ( tabId === 'pro-features' );
		if ( promoWidget.hidden ) { return; }

		const items = promoWidget.querySelectorAll( '.ctc-pro-feature-list li[data-tabs]' );
		if ( ! items.length ) { return; }

		const matches = ( li, key ) => li.dataset.tabs.split( ' ' )
			.includes( key );
		const hasMatch = [ ...items ].some( li => matches( li, tabId ) );

		items.forEach( li => {
			li.hidden = ! matches( li, hasMatch ? tabId : 'default' );
		} );
	}

	/**
	 * Right Sidebar Tabs
	 */
	static initRightSidebar () {
		const tabButtons = document.querySelectorAll( '.sidebar-tab-btn' );
		const tabContents = document.querySelectorAll( '.sidebar-tab-content' );

		const activateTab = ( tabId ) => {
			tabButtons.forEach( btn => btn.classList.toggle( 'active', btn.dataset.sidebarTab === tabId ) );
			tabContents.forEach( content => content.classList.toggle( 'active', content.id === `sidebar-tab-${tabId}` ) );
		};

		const switchTab = ( tabId ) => {
			const targetBtn =
				document.querySelector( `.sidebar-tab-btn[data-sidebar-tab="${tabId}"]` );
			if ( targetBtn?.classList.contains( 'active' ) ) {
				tabButtons.forEach( btn => btn.classList.remove( 'active' ) );
				tabContents.forEach( content =>
					content.classList.remove( 'active' ) );
				return;
			}

			activateTab( tabId );
		};

		tabButtons.forEach( btn => {
			btn.addEventListener( 'click', () => switchTab( btn.dataset.sidebarTab ) );
		} );

		// Programmatic activation (e.g. PreviewManager surfaces a preview note
		// while another tab — or none — is showing). Unlike a click, this never
		// toggles the panel closed: it only makes the requested tab visible.
		document.addEventListener( 'ctc_open_sidebar_tab', ( event ) => {
			const tabId = event.detail?.tab;
			if ( ! tabId ) { return; }
			const targetBtn =
				document.querySelector( `.sidebar-tab-btn[data-sidebar-tab="${tabId}"]` );
			if ( ! targetBtn || targetBtn.classList.contains( 'active' ) ) { return; }
			activateTab( tabId );
		} );
	}
}
