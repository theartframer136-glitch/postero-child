( function( window, $ ) {
	'use strict';

	var config = window.crCaptchaConfig || {};

	var API_OBJECTS = {
		turnstile: 'turnstile',
		hcaptcha: 'hcaptcha',
		recaptcha2: 'grecaptcha',
		recaptcha3: 'grecaptcha'
	};

	var READY_INTERVAL = 100;
	var READY_ATTEMPTS = 150;

	function isInvisible() {
		return "recaptcha3" === config.type;
	}

	function api() {
		var name = API_OBJECTS[config.type];
		return ( name && window[name] ) ? window[name] : null;
	}

	function onApiReady( callback ) {
		var attempts = 0;
		( function check() {
			var provider = api();
			if ( provider && 'function' === typeof provider.render ) {
				callback( provider );
				return;
			}
			attempts++;
			if ( attempts > READY_ATTEMPTS ) {
				return;
			}
			window.setTimeout( check, READY_INTERVAL );
		} )();
	}

	function widgetIn( container ) {
		if ( ! container || ! container.length ) {
			container = $( document );
		}
		var widget = $( container ).find( ".cr-captcha" );
		if ( ! widget.length && $( container ).hasClass( "cr-captcha" ) ) {
			widget = $( container );
		}
		return widget.first();
	}

	function render( container ) {
		if ( ! config.type || isInvisible() ) {
			return;
		}
		onApiReady( function( provider ) {
			var widgets = container ? $( container ).find( ".cr-captcha-widget" ) : $( ".cr-captcha-widget" );
			widgets.each( function() {
				var widget = $( this );
				if ( widget.attr( "data-crcaptcha-rendered" ) ) {
					return;
				}

				widget.attr( "data-crcaptcha-rendered", "1" );
				var params = {
					sitekey: widget.attr( "data-sitekey" ) || config.siteKey
				};

				if ( "turnstile" === config.type ) {
					params.theme = config.theme || "light";
					if ( config.language ) {
						params.language = config.language;
					}
				}

				try {
					widget.data( "crCaptchaWidgetId", provider.render( this, params ) );
				} catch ( e ) {
					widget.removeAttr( "data-crcaptcha-rendered" );
				}
			} );
		} );
	}

	function getResponse( container ) {
		if ( ! config.type ) {
			return "";
		}
		var widget = widgetIn( container );
		if ( ! widget.length ) {
			return "";
		}
		if ( isInvisible() ) {
			return widget.find( ".cr-captcha-response" ).val() || "";
		}
		var provider = api();
		if ( ! provider || 'function' !== typeof provider.getResponse ) {
			return "";
		}
		var widgetId = widget.data( "crCaptchaWidgetId" );
		try {
			if ( undefined === widgetId ) {
				return provider.getResponse() || "";
			}
			return provider.getResponse( widgetId ) || "";
		} catch ( e ) {
			return "";
		}
	}

	function reset( container ) {
		if ( ! config.type ) {
			return;
		}
		var widget = widgetIn( container );
		if ( ! widget.length ) {
			return;
		}
		if ( isInvisible() ) {
			widget.find( ".cr-captcha-response" ).val( "" );
			return;
		}
		var provider = api();
		if ( ! provider || 'function' !== typeof provider.reset ) {
			return;
		}
		var widgetId = widget.data( "crCaptchaWidgetId" );
		try {
			if ( undefined === widgetId ) {
				provider.reset();
			} else {
				provider.reset( widgetId );
			}
		} catch ( e ) {}
	}

	function execute( container, callback ) {
		if ( ! config.type ) {
			callback( "" );
			return;
		}
		if ( ! isInvisible() ) {
			callback( getResponse( container ) );
			return;
		}
		var widget = widgetIn( container );
		var siteKey = widget.attr( "data-sitekey" ) || config.siteKey;
		onApiReady( function( provider ) {
			var run = function() {
				try {
					provider.execute( siteKey, { action: "submit" } ).then( function( token ) {
						widget.find( ".cr-captcha-response" ).val( token );
						callback( token || "" );
					}, function() {
						callback( "" );
					} );
				} catch ( e ) {
					callback( "" );
				}
			};
			if ( 'function' === typeof provider.ready ) {
				provider.ready( run );
			} else {
				run();
			}
		} );
	}

	function consume( container, callback ) {
		execute( container, function( token ) {
			reset( container );
			callback( token );
		} );
	}

	window.crCaptcha = {
		type: function() {
			return config.type || "";
		},
		isEnabled: function() {
			return !! config.type;
		},
		isInvisible: isInvisible,
		responseField: function() {
			return config.responseField || "";
		},
		errorText: function() {
			return config.errorText || "";
		},
		render: render,
		getResponse: getResponse,
		reset: reset,
		execute: execute,
		consume: consume
	};

	$( document ).ready( function() {
		render();
		$( document ).on( "ajaxComplete", function() {
			render();
		} );
	} );

} )( window, jQuery );
