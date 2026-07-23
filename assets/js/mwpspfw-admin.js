/**
 * MainWP for Simple Performance for WordPress - admin interactions.
 *
 * Plain JS (no jQuery dependency beyond what MainWP ships). Handles:
 * - Tab switching on the per-site configuration screen
 * - Conditional field visibility (data-requires-field / data-requires-value)
 * - Form serialization → AJAX POST → re-hydrate
 * - Dirty-form tracking (beforeunload warning)
 * - Overview: per-row Check / Install buttons, bulk select + install
 */

( function () {
	'use strict';

	var admin = window.mwpspfwAdmin || {};
	var dirty = false;

	/* ─── Helpers ─────────────────────────────────────────────────────────── */

	function $( selector, context ) {
		return ( context || document ).querySelector( selector );
	}

	function $$( selector, context ) {
		return Array.prototype.slice.call( ( context || document ).querySelectorAll( selector ) );
	}

	function ajaxPost( data, callback ) {
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', admin.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		xhr.onreadystatechange = function () {
			if ( 4 !== xhr.readyState ) {
				return;
			}
			var response;
			try {
				response = JSON.parse( xhr.responseText );
			} catch ( e ) {
				response = { success: false, data: { message: admin.saveError } };
			}
			callback( response );
		};
		xhr.send( data );
	}

	function serialize( form ) {
		var pairs = [];
		$$( 'input, select, textarea', form ).forEach( function ( el ) {
			if ( ! el.name ) {
				return;
			}
			if ( 'checkbox' === el.type ) {
				if ( el.checked ) {
					pairs.push( encodeURIComponent( el.name ) + '=' + encodeURIComponent( el.value ) );
				}
				return;
			}
			pairs.push( encodeURIComponent( el.name ) + '=' + encodeURIComponent( el.value ) );
		} );
		return pairs.join( '&' );
	}

	/* ─── Tab switching ───────────────────────────────────────────────────── */

	function initTabs() {
		var tabBtns = $$( '.mwpspfw-tab-btn' );
		var panels = $$( '.mwpspfw-tabpanel' );

		if ( ! tabBtns.length ) {
			return;
		}

		tabBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var target = btn.getAttribute( 'data-tab' );

				tabBtns.forEach( function ( b ) {
					b.classList.remove( 'active' );
				} );
				btn.classList.add( 'active' );

				panels.forEach( function ( panel ) {
					if ( panel.getAttribute( 'data-tab' ) === target ) {
						panel.classList.add( 'active' );
						panel.removeAttribute( 'hidden' );
					} else {
						panel.classList.remove( 'active' );
						panel.setAttribute( 'hidden', '' );
					}
				} );
			} );
		} );
	}

	/* ─── Conditional field visibility ────────────────────────────────────── */

	function initConditionalFields() {
		var form = $( '#mwpspfw-settings-form' );
		if ( ! form ) {
			return;
		}

		function evaluateConditions() {
			$$( '.mwpspfw-field[data-requires-field]', form ).forEach( function ( field ) {
				var reqName = field.getAttribute( 'data-requires-field' );
				var reqValue = field.getAttribute( 'data-requires-value' );

				// Convert dot-notation to bracket notation for form lookup.
				var parts = reqName.split( '.' );
				var inputName = 'fields[' + parts[0] + '][' + parts[1] + ']';

				var input = form.querySelector( '[name="' + inputName + '"]' );
				var visible = false;

				if ( input ) {
					if ( 'checkbox' === input.type ) {
						visible = input.checked && ( '1' === reqValue );
					} else {
						visible = input.value === reqValue;
					}
				}

				field.style.display = visible ? '' : 'none';
			} );
		}

		// Evaluate on load and on any input change.
		evaluateConditions();
		form.addEventListener( 'change', evaluateConditions );
		form.addEventListener( 'input', evaluateConditions );
	}

	/* ─── Dirty form tracking ─────────────────────────────────────────────── */

	function initDirtyTracking() {
		var form = $( '#mwpspfw-settings-form' );
		if ( ! form ) {
			return;
		}

		form.addEventListener( 'input', function () {
			dirty = true;
		} );
		form.addEventListener( 'change', function () {
			dirty = true;
		} );

		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = admin.confirmUnsaved;
				return admin.confirmUnsaved;
			}
		} );
	}

	/* ─── Settings form save ──────────────────────────────────────────────── */

	function initSaveForm() {
		var form = $( '#mwpspfw-settings-form' );
		if ( ! form ) {
			return;
		}

		var saveBtn = $( '#mwpspfw-save-btn' );
		var status = $( '#mwpspfw-save-status' );
		var notice = $( '#mwpspfw-notice' );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			if ( saveBtn ) {
				saveBtn.disabled = true;
			}
			if ( status ) {
				status.textContent = admin.saving;
			}
			if ( notice ) {
				notice.hidden = true;
			}

			var data = serialize( form );

			ajaxPost( data, function ( response ) {
				if ( saveBtn ) {
					saveBtn.disabled = false;
				}

				if ( response.success ) {
					dirty = false;
					if ( status ) {
						status.textContent = admin.saved;
					}

					// Re-hydrate form with the child's post-save state.
					if ( response.data && response.data.settings ) {
						rehydrateForm( form, response.data.settings );
					}
				} else {
					var msg = ( response.data && response.data.message ) ? response.data.message : admin.saveError;
					if ( notice ) {
						notice.textContent = msg;
						notice.hidden = false;
					}
					if ( status ) {
						status.textContent = '';
					}
				}
			} );
		} );
	}

	function rehydrateForm( form, settings ) {
		// Flatten nested settings and update form inputs.
		Object.keys( settings ).forEach( function ( group ) {
			if ( 'object' !== typeof settings[ group ] || null === settings[ group ] ) {
				return;
			}
			Object.keys( settings[ group ] ).forEach( function ( key ) {
				var inputName = 'fields[' + group + '][' + key + ']';
				var inputs = form.querySelectorAll( '[name="' + inputName + '"]' );
				var value = settings[ group ][ key ];

				inputs.forEach( function ( input ) {
					if ( 'checkbox' === input.type ) {
						input.checked = ( true === value || '1' === value || 1 === value );
					} else if ( 'textarea' === input.tagName.toLowerCase() ) {
						if ( Array.isArray( value ) ) {
							input.value = value.join( '\n' );
						} else {
							input.value = value;
						}
					} else {
						input.value = value;
					}
				} );
			} );
		} );

		// Re-evaluate conditional visibility after rehydration.
		var event = new Event( 'change', { bubbles: true } );
		form.dispatchEvent( event );
	}

	/* ─── Overview: Check button ──────────────────────────────────────────── */

	function initCheckButtons() {
		$$( '.mwpspfw-check-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var siteId = btn.getAttribute( 'data-site-id' );
				var row = btn.closest( 'tr' );
				var statusCell = row ? row.querySelector( '.mwpspfw-status-cell' ) : null;

				if ( statusCell ) {
					statusCell.textContent = admin.checking;
				}
				btn.disabled = true;

				var data = 'action=mwpspfw_check_site&nonce=' + encodeURIComponent( admin.checkNonce ) + '&site_id=' + encodeURIComponent( siteId );

				ajaxPost( data, function ( response ) {
					btn.disabled = false;

					if ( response.success && statusCell ) {
						var d = response.data;
						var parts = [];
						if ( d.version ) {
							parts.push( 'v' + d.version );
						}
						if ( typeof d.active_toggles !== 'undefined' ) {
							parts.push( d.active_toggles + ' core toggles' );
						}
						if ( d.csp_enabled ) {
							parts.push( 'CSP on' );
						}
						if ( d.fonts_localized ) {
							parts.push( 'Fonts localized' );
						}
						statusCell.textContent = parts.length ? parts.join( ' · ' ) : '✓';
					} else if ( statusCell ) {
						var msg = ( response.data && response.data.message ) ? response.data.message : 'Error';
						statusCell.textContent = '✗ ' + msg;
					}
				} );
			} );
		} );
	}

	/* ─── Overview: Install button ────────────────────────────────────────── */

	function initInstallButtons() {
		$$( '.mwpspfw-install-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var siteId = btn.getAttribute( 'data-site-id' );

				if ( ! window.confirm( admin.confirmInstall ) ) {
					return;
				}

				var row = btn.closest( 'tr' );
				var statusCell = row ? row.querySelector( '.mwpspfw-status-cell' ) : null;

				if ( statusCell ) {
					statusCell.textContent = admin.installing;
				}
				btn.disabled = true;

				var data = 'action=mwpspfw_install_spfw&nonce=' + encodeURIComponent( admin.installNonce ) + '&site_id=' + encodeURIComponent( siteId );

				ajaxPost( data, function ( response ) {
					btn.disabled = false;

					if ( response.success && statusCell ) {
						statusCell.textContent = admin.installed;
					} else if ( statusCell ) {
						var msg = ( response.data && response.data.message ) ? response.data.message : 'Error';
						statusCell.textContent = '✗ ' + msg;
					}
				} );
			} );
		} );
	}

	/* ─── Overview: Bulk select + install ─────────────────────────────────── */

	function initBulkActions() {
		var selectAll = $( '#mwpspfw-select-all' );
		var bulkBtn = $( '#mwpspfw-bulk-install-btn' );
		var bulkCount = $( '#mwpspfw-bulk-count' );
		var bulkStatus = $( '#mwpspfw-bulk-status' );
		var siteCheckboxes = $$( '.mwpspfw-site-checkbox' );

		if ( ! siteCheckboxes.length ) {
			return;
		}

		function updateBulkUI() {
			var selected = siteCheckboxes.filter( function ( cb ) {
				return cb.checked;
			} );
			var count = selected.length;

			if ( bulkBtn ) {
				bulkBtn.disabled = 0 === count;
			}
			if ( bulkCount ) {
				bulkCount.textContent = count ? admin.bulkSelected.replace( '%d', count ) : '';
			}
			if ( selectAll ) {
				selectAll.checked = count === siteCheckboxes.length && count > 0;
				selectAll.indeterminate = count > 0 && count < siteCheckboxes.length;
			}
		}

		if ( selectAll ) {
			selectAll.addEventListener( 'change', function () {
				siteCheckboxes.forEach( function ( cb ) {
					cb.checked = selectAll.checked;
				} );
				updateBulkUI();
			} );
		}

		siteCheckboxes.forEach( function ( cb ) {
			cb.addEventListener( 'change', updateBulkUI );
		} );

		if ( bulkBtn ) {
			bulkBtn.addEventListener( 'click', function () {
				var selected = siteCheckboxes.filter( function ( cb ) {
					return cb.checked;
				} );

				if ( ! selected.length ) {
					if ( bulkStatus ) {
						bulkStatus.textContent = admin.bulkNone;
					}
					return;
				}

				if ( ! window.confirm( admin.bulkConfirm ) ) {
					return;
				}

				var total = selected.length;
				var done = 0;
				var succeeded = 0;
				var failed = 0;

				bulkBtn.disabled = true;

				function installNext() {
					if ( done >= total ) {
						bulkBtn.disabled = false;
						if ( bulkStatus ) {
							bulkStatus.textContent = admin.bulkDone.replace( '%1$d', succeeded ).replace( '%2$d', failed );
						}
						return;
					}

					var siteId = selected[ done ].value;
					if ( bulkStatus ) {
						bulkStatus.textContent = admin.bulkInstalling.replace( '%1$d', done + 1 ).replace( '%2$d', total );
					}

					var data = 'action=mwpspfw_install_spfw&nonce=' + encodeURIComponent( admin.installNonce ) + '&site_id=' + encodeURIComponent( siteId );

					ajaxPost( data, function ( response ) {
						done++;
						if ( response.success ) {
							succeeded++;
						} else {
							failed++;
						}
						installNext();
					} );
				}

				installNext();
			} );
		}

		updateBulkUI();
	}

	/* ─── Init ────────────────────────────────────────────────────────────── */

	function init() {
		initTabs();
		initConditionalFields();
		initDirtyTracking();
		initSaveForm();
		initCheckButtons();
		initInstallButtons();
		initBulkActions();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
