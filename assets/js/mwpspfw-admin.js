/**
 * MainWP for Simple Performance for WordPress - admin interactions.
 *
 * Plain JS (no jQuery dependency beyond what MainWP ships). Handles:
 * - Tab switching on the per-site configuration screen
 * - Conditional field visibility (data-requires-field / data-requires-value)
 * - Form serialization → AJAX POST → re-hydrate
 * - Post-save re-hydration of scalar, multi_check, composite (Permissions-
 *   Policy, CSP directives) and hardening-status-badge state
 * - Dirty-form tracking (beforeunload warning)
 * - File integrity review (per-site Hardening tab): pending changes, Scan
 *   now, Mark as verified per scope
 * - Overview: per-row Check / Install buttons, bulk select + install
 * - Overview: fleet file integrity comparison, differences, consensus verify
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

	/**
	 * Build an element with an optional class and text. Child-supplied
	 * strings (paths, slugs, versions) only ever go through textContent.
	 */
	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( 'undefined' !== typeof text && null !== text ) {
			node.textContent = String( text );
		}
		return node;
	}

	function fmt( template ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( template || '' ).replace( /%(\d+\$)?[sd]/g, function ( match, pos ) {
			var idx = pos ? parseInt( pos, 10 ) - 1 : i++;
			return 'undefined' !== typeof args[ idx ] ? String( args[ idx ] ) : match;
		} );
	}

	/**
	 * Pick the singular or plural form of a localized {one, other} pair.
	 * A plain string passes through unchanged.
	 */
	function plural( forms, n ) {
		if ( forms && 'object' === typeof forms ) {
			return 1 === Number( n ) ? forms.one : forms.other;
		}
		return forms;
	}

	function formatTime( seconds ) {
		return seconds ? new Date( seconds * 1000 ).toLocaleString() : ( admin.fileReview ? admin.fileReview.never : '' );
	}

	function ajaxQuery( params ) {
		return Object.keys( params ).map( function ( k ) {
			return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] );
		} ).join( '&' );
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
				var inputName = 'fields[' + parts[ 0 ] + '][' + parts[ 1 ] + ']';

				// Toggles render a hidden '0' twin before their checkbox, and
				// querySelector returns the first match — prefer the checkbox so
				// the condition tracks its checked state, not the twin's '0'.
				var input = form.querySelector( '[name="' + inputName + '"][type="checkbox"]' )
					|| form.querySelector( '[name="' + inputName + '"]' );
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
						form.dispatchEvent( new CustomEvent( 'mwpspfw:settings', { detail: response.data.settings } ) );
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
					if ( 'hidden' === input.type ) {
						// The hidden '0' twin of a toggle checkbox must stay '0';
						// writing the stored boolean here would post the string
						// 'false' for every unchecked toggle on the next save.
						return;
					}
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

				// multi_check groups post as fields[group][key][]; check the boxes
				// whose value is in the stored array and clear the rest.
				if ( Array.isArray( value ) ) {
					var vals = value.map( function ( v ) { return String( v ); } );
					$$( '[name="' + inputName + '[]"]', form ).forEach( function ( box ) {
						box.checked = vals.indexOf( box.value ) !== -1;
					} );
				}

				// Composites post one level deeper than the plain field name.
				if ( 'hardening' === group && 'permissions_policy' === key ) {
					rehydratePermissionsPolicy( form, value );
				}
				if ( 'hardening' === group && 'csp_directives' === key ) {
					rehydrateCspDirectives( form, value );
				}
			} );
		} );

		rehydrateStatusBadges( form, settings );

		// Re-evaluate conditional visibility after rehydration.
		var event = new Event( 'change', { bubbles: true } );
		form.dispatchEvent( event );
	}

	/**
	 * Permissions-Policy composite: feature => tokens[] (blocked) | false.
	 * Blocked features check their box and fill the origins input with the
	 * space-separated allowlist; unblocked features clear both. Features the
	 * child dropped (unblocked) keep their rendered unchecked state.
	 */
	function rehydratePermissionsPolicy( form, policy ) {
		if ( 'object' !== typeof policy || null === policy ) {
			return;
		}
		Object.keys( policy ).forEach( function ( feature ) {
			var tokens = policy[ feature ];
			var blocked = Array.isArray( tokens );
			var base = 'fields[hardening][permissions_policy][' + feature + ']';
			var blockBox = form.querySelector( 'input[type="checkbox"][name="' + base + '[block]"]' );
			var origins = form.querySelector( '[name="' + base + '[origins]"]' );
			if ( blockBox ) {
				blockBox.checked = blocked;
			}
			if ( origins ) {
				origins.value = blocked ? tokens.join( ' ' ) : '';
			}
		} );
	}

	/**
	 * CSP-directives composite: directive => tokens[]. The child preserves
	 * empty token lists, so every posted directive comes back and the input
	 * is set to the space-joined list (empty clears the field).
	 */
	function rehydrateCspDirectives( form, directives ) {
		if ( 'object' !== typeof directives || null === directives ) {
			return;
		}
		Object.keys( directives ).forEach( function ( directive ) {
			var tokens = directives[ directive ];
			if ( ! Array.isArray( tokens ) ) {
				return;
			}
			var input = form.querySelector( '[name="fields[hardening][csp_directives][' + directive + '"]' );
			if ( input ) {
				input.value = tokens.join( ' ' );
			}
		} );
	}

	/**
	 * Hardening status badges: 'hardening_status' / 'uploads_hardening_status'
	 * are top-level strings in the settings payload, not a field group. Tone
	 * map mirrors render_status_badge() in class-mwpspfw-individual.php.
	 */
	function rehydrateStatusBadges( form, settings ) {
		var tone = {
			ok: 'positive',
			missing: 'negative',
			altered: 'warning',
			unsupported: 'basic',
			advisory: 'basic'
		};
		$$( '.mwpspfw-status-badge', form ).forEach( function ( badge ) {
			var key = badge.getAttribute( 'data-status-key' );
			var status = key ? settings[ key ] : null;
			if ( 'string' !== typeof status || ! tone[ status ] ) {
				return;
			}
			badge.className = 'ui ' + tone[ status ] + ' label mwpspfw-status-badge';
			badge.textContent = ( admin.statusLabels && admin.statusLabels[ status ] ) ? admin.statusLabels[ status ] : status;
		} );
	}

	/* ─── Per-site: File integrity review ─────────────────────────────────── */

	/**
	 * Group the child's pending set by verification scope: 'uploads', or a
	 * plugin slug. Each group lists its unexplained paths and, for a plugin,
	 * the unverified version change if there is one.
	 */
	function reviewGroups( pending ) {
		var groups = {};
		var order = [];
		var unexplained = ( pending && pending.unexplained ) || {};
		var updates = ( pending && pending.updates ) || {};

		function group( scope ) {
			if ( ! groups[ scope ] ) {
				groups[ scope ] = { scope: scope, update: null, paths: [] };
				order.push( scope );
			}
			return groups[ scope ];
		}

		function scopeOf( path ) {
			if ( 0 === path.indexOf( 'uploads/' ) ) {
				return 'uploads';
			}
			var parts = path.split( '/' );
			return parts.length > 1 ? parts[ 1 ] : path;
		}

		[ 'added', 'modified', 'removed' ].forEach( function ( type ) {
			( unexplained[ type ] || [] ).forEach( function ( path ) {
				group( scopeOf( path ) ).paths.push( { type: type, path: path } );
			} );
		} );

		Object.keys( updates ).forEach( function ( slug ) {
			var u = updates[ slug ] || {};
			var g = group( slug );
			g.update = { from: u.from || '', to: u.to || '' };
			[ 'added', 'modified', 'removed' ].forEach( function ( type ) {
				( ( u.paths && u.paths[ type ] ) || [] ).forEach( function ( path ) {
					g.paths.push( { type: type, path: path } );
				} );
			} );
		} );

		return order.map( function ( scope ) {
			return groups[ scope ];
		} );
	}

	function scopeSignature( signatures, scope ) {
		if ( ! signatures ) {
			return '';
		}
		if ( 'all' === scope || 'uploads' === scope ) {
			return signatures[ scope ] || '';
		}
		return ( signatures.plugins && signatures.plugins[ scope ] ) || '';
	}

	function renderFileReview( container, state ) {
		var t = admin.fileReview || {};
		var body = $( '.mwpspfw-file-review-body', container );
		if ( ! body || ! state ) {
			return;
		}

		body.textContent = '';

		var verified = state.verified || {};
		var meta = el( 'p', 'mwpspfw-file-review-meta' );
		meta.appendChild( el( 'span', '', fmt( plural( t.lastScan, state.snapshot_count || 0 ), formatTime( state.last_scan ), state.snapshot_count || 0 ) ) );
		if ( verified.time ) {
			var source = ( t.sources && t.sources[ verified.source ] ) || verified.source || '';
			var who = verified.user ? fmt( t.byUser, verified.user ) : '';
			meta.appendChild( el( 'br' ) );
			meta.appendChild( el( 'span', '', fmt( t.lastVerified, formatTime( verified.time ), source ) + who ) );
		}
		body.appendChild( meta );

		var pending = state.pending || {};
		var groups = reviewGroups( pending );

		if ( ! groups.length ) {
			body.appendChild( el( 'div', 'ui positive message', t.nothingPending ) );
			return;
		}

		groups.forEach( function ( g ) {
			var box = el( 'div', 'mwpspfw-file-review-group' );
			var head = el( 'div', 'mwpspfw-file-review-group-head' );
			var title = el( 'strong', '', 'uploads' === g.scope ? t.uploadsScope : g.scope );
			head.appendChild( title );

			if ( g.update ) {
				head.appendChild( el( 'span', 'ui basic label', fmt( t.unverifiedUpdate, g.update.from || '?', g.update.to || '?' ) ) );
			}

			var sig = scopeSignature( pending.signatures, g.scope );
			if ( sig ) {
				var btn = el( 'button', 'ui mini button mwpspfw-verify-files-btn', t.markVerified );
				btn.type = 'button';
				btn.setAttribute( 'data-scope', g.scope );
				btn.setAttribute( 'data-signature', sig );
				head.appendChild( btn );
			}
			box.appendChild( head );

			if ( g.paths.length ) {
				var list = el( 'ul', 'mwpspfw-file-list' );
				g.paths.forEach( function ( item ) {
					var li = el( 'li' );
					li.appendChild( el( 'span', 'mwpspfw-file-change mwpspfw-file-change-' + item.type, ( t.types && t.types[ item.type ] ) || item.type ) );
					li.appendChild( el( 'code', '', item.path ) );
					list.appendChild( li );
				} );
				box.appendChild( list );
			}

			body.appendChild( box );
		} );

		var allSig = scopeSignature( pending.signatures, 'all' );
		if ( groups.length > 1 && allSig ) {
			var all = el( 'button', 'ui button mwpspfw-verify-files-btn', t.markAllVerified );
			all.type = 'button';
			all.setAttribute( 'data-scope', 'all' );
			all.setAttribute( 'data-signature', allSig );
			body.appendChild( el( 'p' ) ).appendChild( all );
		}
	}

	function initFileReview() {
		var container = $( '#mwpspfw-file-review' );
		var form = $( '#mwpspfw-settings-form' );
		if ( ! container || '1' !== container.getAttribute( 'data-supported' ) || ! form ) {
			return;
		}

		var t = admin.fileReview || {};
		var siteId = form.getAttribute( 'data-site-id' );
		var status = $( '.mwpspfw-file-review-status', container );
		var busy = false;
		var state;

		try {
			state = JSON.parse( container.getAttribute( 'data-state' ) || '{}' );
		} catch ( e ) {
			state = {};
		}
		renderFileReview( container, state );

		function run( params, workingText ) {
			if ( busy ) {
				return;
			}
			busy = true;
			if ( status ) {
				status.textContent = workingText;
			}
			$$( 'button', container ).forEach( function ( b ) {
				b.disabled = true;
			} );

			params.nonce = admin.fileReviewNonce;
			params.site_id = siteId;

			ajaxPost( ajaxQuery( params ), function ( response ) {
				busy = false;
				$$( 'button', container ).forEach( function ( b ) {
					b.disabled = false;
				} );

				if ( response.success && response.data && response.data.file_monitor ) {
					renderFileReview( container, response.data.file_monitor );
					if ( status ) {
						status.textContent = t.done;
					}
					return;
				}

				var d = response.data || {};
				if ( status ) {
					status.textContent = '✗ ' + ( 'spfw_files_changed' === d.code ? t.changedSinceReview : ( d.message || admin.saveError ) );
				}
			} );
		}

		container.addEventListener( 'click', function ( e ) {
			var target = e.target;
			if ( ! target || 'BUTTON' !== target.tagName ) {
				return;
			}

			if ( target.classList.contains( 'mwpspfw-scan-files-btn' ) ) {
				run( { action: 'mwpspfw_scan_files' }, t.scanning );
				return;
			}

			if ( target.classList.contains( 'mwpspfw-verify-files-btn' ) ) {
				var scope = target.getAttribute( 'data-scope' );
				var label = 'all' === scope ? t.allScope : ( 'uploads' === scope ? t.uploadsScope : scope );
				if ( ! window.confirm( fmt( t.confirmVerify, label ) ) ) {
					return;
				}
				run( {
					action: 'mwpspfw_verify_files',
					scope: scope,
					signature: target.getAttribute( 'data-signature' )
				}, t.verifying );
			}
		} );

		// A settings save re-reads the child, so the review refreshes too.
		form.addEventListener( 'mwpspfw:settings', function ( e ) {
			if ( e.detail && e.detail.file_monitor ) {
				renderFileReview( container, e.detail.file_monitor );
			}
		} );
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
						if ( d.integrity_pending ) {
							parts.push( fmt( plural( admin.fileReview && admin.fileReview.pendingCount, d.integrity_pending ), d.integrity_pending ) );
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
				var errors = [];

				bulkBtn.disabled = true;

				function installNext() {
					if ( done >= total ) {
						bulkBtn.disabled = false;
						if ( bulkStatus ) {
							var summary = admin.bulkDone.replace( '%1$d', succeeded ).replace( '%2$d', failed );
							// Show first error if all failed.
							if ( failed > 0 && errors.length > 0 ) {
								summary += ' ' + errors[ 0 ];
							}
							bulkStatus.textContent = summary;
						}
						// Log all errors to console for debugging.
						if ( errors.length > 0 ) {
							console.error( 'SPFW Bulk Install Errors:', errors );
						}
						return;
					}

					var cb = selected[ done ];
					var siteId = cb.value;
					var row = cb.closest( 'tr' );
					var statusCell = row ? row.querySelector( '.mwpspfw-status-cell' ) : null;

					if ( bulkStatus ) {
						bulkStatus.textContent = admin.bulkInstalling.replace( '%1$d', done + 1 ).replace( '%2$d', total );
					}
					if ( statusCell ) {
						statusCell.textContent = admin.installing;
					}

					var data = 'action=mwpspfw_install_spfw&nonce=' + encodeURIComponent( admin.installNonce ) + '&site_id=' + encodeURIComponent( siteId );

					ajaxPost( data, function ( response ) {
						done++;
						if ( response.success ) {
							succeeded++;
							if ( statusCell ) {
								statusCell.textContent = admin.installed;
							}
						} else {
							failed++;
							var msg = ( response.data && response.data.message ) ? response.data.message : 'Unknown error';
							if ( statusCell ) {
								statusCell.textContent = '✗ ' + msg;
							}
							// Collect unique errors.
							if ( errors.indexOf( msg ) === -1 ) {
								errors.push( msg );
							}
						}
						installNext();
					} );
				}

				installNext();
			} );
		}

		updateBulkUI();
	}

	/* ─── Overview: Fleet file integrity ──────────────────────────────────── */

	function initFleetIntegrity() {
		var root = $( '#mwpspfw-fleet' );
		if ( ! root ) {
			return;
		}

		var t = admin.fleet || {};
		var runBtn = $( '#mwpspfw-fleet-run-btn' );
		var status = $( '#mwpspfw-fleet-status' );
		var out = $( '#mwpspfw-fleet-report' );
		var canVerify = '1' === root.getAttribute( 'data-can-verify' );
		var sites;
		var report;

		try {
			sites = JSON.parse( root.getAttribute( 'data-sites' ) || '{}' ) || {};
			report = JSON.parse( root.getAttribute( 'data-report' ) || 'null' );
		} catch ( e ) {
			sites = {};
			report = null;
		}

		function siteName( id ) {
			var s = sites[ id ];
			return s ? ( s.name || s.url || ( '#' + id ) ) : ( '#' + id );
		}

		function siteLink( id ) {
			var s = sites[ id ];
			var a = el( 'a', '', siteName( id ) );
			if ( s && s.link ) {
				a.href = s.link;
			}
			return a;
		}

		function post( params, cb ) {
			params.nonce = admin.integrityNonce;
			ajaxPost( ajaxQuery( params ), cb );
		}

		function renderDiff( holder, data ) {
			holder.textContent = '';
			holder.appendChild( el( 'p', '', fmt( t.diffAgainst, siteName( data.reference ) ) ) );
			var diff = data.diff || {};
			var any = false;
			var list = el( 'ul', 'mwpspfw-file-list' );
			[ 'added', 'modified', 'removed' ].forEach( function ( type ) {
				( diff[ type ] || [] ).forEach( function ( path ) {
					any = true;
					var li = el( 'li' );
					var types = ( admin.fileReview && admin.fileReview.types ) || {};
					li.appendChild( el( 'span', 'mwpspfw-file-change mwpspfw-file-change-' + type, types[ type ] || type ) );
					li.appendChild( el( 'code', '', path ) );
					list.appendChild( li );
				} );
			} );
			holder.appendChild( any ? list : el( 'p', '', t.diffNone ) );
		}

		function outlierItem( group, siteId ) {
			var li = el( 'li', 'mwpspfw-fleet-outlier' );
			li.appendChild( siteLink( siteId ) );
			var btn = el( 'button', 'ui mini button', t.showDiff );
			btn.type = 'button';
			var holder = el( 'div', 'mwpspfw-fleet-diff' );
			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				holder.textContent = t.loadingDiff;
				post( { action: 'mwpspfw_integrity_diff', slug: group.slug, version: group.version, site_id: siteId }, function ( response ) {
					btn.disabled = false;
					if ( response.success ) {
						renderDiff( holder, response.data );
					} else {
						holder.textContent = '✗ ' + ( ( response.data && response.data.message ) || admin.saveError );
					}
				} );
			} );
			li.appendChild( document.createTextNode( ' ' ) );
			li.appendChild( btn );
			li.appendChild( holder );
			return li;
		}

		function consensusControl( group ) {
			var wrap = el( 'div', 'mwpspfw-fleet-consensus' );
			var btn = el( 'button', 'ui mini green button', fmt( plural( t.verifyButton, group.verifiable.length ), group.verifiable.length ) );
			btn.type = 'button';
			var results = el( 'ul', 'mwpspfw-fleet-consensus-results' );

			btn.addEventListener( 'click', function () {
				var excluded = group.outliers.length ? group.outliers.map( siteName ).join( ', ' ) : t.none;
				if ( ! window.confirm( fmt( t.confirmConsensus, group.slug, group.version, group.majority_sites.length, group.verifiable.length, excluded ) ) ) {
					return;
				}

				var queue = group.verifiable.slice();
				btn.disabled = true;
				results.textContent = '';

				function next() {
					if ( ! queue.length ) {
						btn.disabled = ! group.verifiable.length;
						btn.textContent = fmt( plural( t.verifyButton, group.verifiable.length ), group.verifiable.length );
						btn.hidden = ! group.verifiable.length;
						return;
					}
					var siteId = queue.shift();
					var li = el( 'li' );
					li.appendChild( siteLink( siteId ) );
					var state = el( 'span', '', ' — ' + t.verifyingSite );
					li.appendChild( state );
					results.appendChild( li );

					post( { action: 'mwpspfw_integrity_consensus', slug: group.slug, version: group.version, site_id: siteId }, function ( response ) {
						if ( response.success ) {
							state.textContent = ' — ' + t.verifiedSite;
							group.verifiable = group.verifiable.filter( function ( id ) {
								return id !== siteId;
							} );
							group.pending = group.pending.filter( function ( id ) {
								return id !== siteId;
							} );
						} else {
							var d = response.data || {};
							state.textContent = ' — ✗ ' + ( 'spfw_files_changed' === d.code ? t.changedSince : ( d.message || admin.saveError ) );
						}
						next();
					} );
				}

				next();
			} );

			wrap.appendChild( btn );
			wrap.appendChild( results );
			return wrap;
		}

		function groupRow( group ) {
			var tr = el( 'tr', 'mwpspfw-fleet-row mwpspfw-fleet-' + group.verdict );
			var name = el( 'td' );
			name.appendChild( el( 'strong', '', group.slug ) );
			name.appendChild( el( 'span', 'mwpspfw-fleet-version', ' ' + group.version ) );
			tr.appendChild( name );
			tr.appendChild( el( 'td', '', group.sites ) );

			var tone = { outlier: 'red', no_majority: 'orange', consistent: 'green', single: 'basic' }[ group.verdict ] || 'basic';
			var verdict = el( 'td' );
			verdict.appendChild( el( 'span', 'ui ' + tone + ' label', ( t.verdicts && t.verdicts[ group.verdict ] ) || group.verdict ) );
			tr.appendChild( verdict );

			var detail = el( 'td' );
			if ( group.outliers.length ) {
				detail.appendChild( el( 'div', '', t.outlierLabel ) );
				var list = el( 'ul', 'mwpspfw-fleet-outliers' );
				group.outliers.forEach( function ( id ) {
					list.appendChild( outlierItem( group, id ) );
				} );
				detail.appendChild( list );
			}
			if ( 'no_majority' === group.verdict ) {
				detail.appendChild( el( 'p', '', t.noMajorityHelp ) );
				var variants = el( 'ul', 'mwpspfw-fleet-variants' );
				Object.keys( group.variants || {} ).forEach( function ( fp ) {
					var li = el( 'li' );
					li.appendChild( el( 'code', '', fp.substring( 0, 12 ) ) );
					li.appendChild( document.createTextNode( ': ' ) );
					group.variants[ fp ].forEach( function ( id, i ) {
						if ( i ) {
							li.appendChild( document.createTextNode( ', ' ) );
						}
						li.appendChild( siteLink( id ) );
					} );
					variants.appendChild( li );
				} );
				detail.appendChild( variants );
			}
			if ( group.pending.length ) {
				detail.appendChild( el( 'div', 'mwpspfw-fleet-pending', fmt( t.pendingLabel, group.pending.length ) ) );
			}
			if ( canVerify && group.verifiable.length ) {
				detail.appendChild( consensusControl( group ) );
			}
			tr.appendChild( detail );
			return tr;
		}

		function renderReport() {
			out.textContent = '';

			if ( ! report || ! report.groups ) {
				out.appendChild( el( 'p', '', t.noReport ) );
				return;
			}

			out.appendChild( el( 'p', 'mwpspfw-fleet-meta', fmt( plural( t.reportMeta, report.sites_compared || 0 ), formatTime( report.time ), report.user || '', report.sites_compared || 0 ) ) );

			// Only rows that need a look; identical and single-site versions
			// are summarized.
			var shown = report.groups.filter( function ( g ) {
				return 'outlier' === g.verdict || 'no_majority' === g.verdict || g.pending.length;
			} );
			var counts = { consistent: 0, single: 0 };
			report.groups.forEach( function ( g ) {
				if ( shown.indexOf( g ) === -1 && 'undefined' !== typeof counts[ g.verdict ] ) {
					counts[ g.verdict ]++;
				}
			} );

			if ( shown.length ) {
				var table = el( 'table', 'ui unstackable table mwpspfw-fleet-table' );
				var head = el( 'tr' );
				[ t.colPlugin, t.colSites, t.colVerdict, t.colDetail ].forEach( function ( h ) {
					head.appendChild( el( 'th', '', h ) );
				} );
				table.appendChild( el( 'thead' ) ).appendChild( head );
				var tbody = el( 'tbody' );
				shown.forEach( function ( g ) {
					tbody.appendChild( groupRow( g ) );
				} );
				table.appendChild( tbody );
				out.appendChild( table );
			} else {
				out.appendChild( el( 'div', 'ui positive message', t.allConsistent ) );
			}

			var summary = [];
			if ( counts.consistent ) {
				summary.push( counts.consistent + ' ' + ( t.verdicts.consistent || '' ).toLowerCase() );
			}
			if ( counts.single ) {
				summary.push( counts.single + ' ' + ( t.verdicts.single || '' ).toLowerCase() );
			}
			if ( summary.length ) {
				out.appendChild( el( 'p', 'mwpspfw-field-description', summary.join( ' · ' ) ) );
			}

			var uploads = report.uploads_pending || {};
			if ( Object.keys( uploads ).length ) {
				out.appendChild( el( 'h4', 'ui header', t.uploadsHeading ) );
				var ul = el( 'ul', 'mwpspfw-fleet-uploads' );
				Object.keys( uploads ).forEach( function ( id ) {
					var li = el( 'li' );
					li.appendChild( siteLink( id ) );
					li.appendChild( document.createTextNode( ': ' + fmt( plural( t.uploadsPending, uploads[ id ] ), uploads[ id ] ) ) );
					ul.appendChild( li );
				} );
				out.appendChild( ul );
			}

			var skipped = report.skipped || {};
			var skippedIds = Object.keys( skipped );
			if ( skippedIds.length ) {
				out.appendChild( el( 'p', 'mwpspfw-fleet-skipped-head', fmt( plural( t.skippedLabel, skippedIds.length ), skippedIds.length ) ) );
				var sl = el( 'ul', 'mwpspfw-fleet-skipped' );
				skippedIds.forEach( function ( id ) {
					var li = el( 'li' );
					li.appendChild( siteLink( id ) );
					li.appendChild( document.createTextNode( ': ' + skipped[ id ] ) );
					sl.appendChild( li );
				} );
				out.appendChild( sl );
			}
		}

		function randomId() {
			var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
			var id = '';
			for ( var i = 0; i < 16; i++ ) {
				id += chars.charAt( Math.floor( Math.random() * chars.length ) );
			}
			return id;
		}

		if ( runBtn ) {
			runBtn.addEventListener( 'click', function () {
				var boxes = $$( '.mwpspfw-site-checkbox' );
				var chosen = boxes.filter( function ( cb ) {
					return cb.checked;
				} );
				var ids = ( chosen.length ? chosen : boxes ).map( function ( cb ) {
					return cb.value;
				} );

				if ( ! ids.length ) {
					status.textContent = t.noSites;
					return;
				}

				var runId = randomId();
				var i = 0;
				runBtn.disabled = true;

				function collectNext() {
					if ( i >= ids.length ) {
						status.textContent = t.analyzing;
						post( { action: 'mwpspfw_integrity_analyze', run_id: runId }, function ( response ) {
							runBtn.disabled = false;
							if ( response.success && response.data && response.data.report ) {
								report = response.data.report;
								status.textContent = '';
								renderReport();
							} else {
								status.textContent = '✗ ' + ( ( response.data && response.data.message ) || admin.saveError );
							}
						} );
						return;
					}

					status.textContent = fmt( t.collecting, i + 1, ids.length );
					post( { action: 'mwpspfw_integrity_collect', run_id: runId, site_id: ids[ i ] }, function ( response ) {
						if ( ! response.success ) {
							// A gate or nonce failure stops the run; a site that
							// could not be read is recorded as skipped server-side.
							runBtn.disabled = false;
							status.textContent = '✗ ' + ( ( response.data && response.data.message ) || admin.saveError );
							return;
						}
						i++;
						collectNext();
					} );
				}

				collectNext();
			} );
		}

		renderReport();
	}

	/* ─── Overview: Package form with drag-and-drop upload ────────────────── */

	function initPackageForm() {
		var form = $( '#mwpspfw-package-form' );
		if ( ! form ) {
			return;
		}

		var status     = $( '#mwpspfw-package-status' );
		var urlInput   = $( '#mwpspfw-package-url' );
		var fileInput  = $( '#mwpspfw-file-input' );
		var fileHidden = $( '#mwpspfw-package-file' );
		var dropzone   = $( '#mwpspfw-dropzone' );
		var selectBtn  = $( '#mwpspfw-select-file-btn' );
		var prompt     = $( '#mwpspfw-dropzone-prompt' );
		var preview    = $( '#mwpspfw-dropzone-preview' );
		var filenameEl = $( '#mwpspfw-dropzone-filename' );
		var removeBtn  = $( '#mwpspfw-dropzone-remove' );
		var errorEl    = $( '#mwpspfw-dropzone-error' );

		var MAX_SIZE = 10 * 1024 * 1024; // 10 MB

		function showError( msg ) {
			if ( errorEl ) {
				errorEl.textContent = msg;
				errorEl.hidden = false;
			}
		}

		function clearError() {
			if ( errorEl ) {
				errorEl.textContent = '';
				errorEl.hidden = true;
			}
		}

		function showPreview( name ) {
			if ( filenameEl ) {
				filenameEl.textContent = name;
			}
			if ( prompt ) {
				prompt.hidden = true;
			}
			if ( preview ) {
				preview.hidden = false;
			}
		}

		function setFileMode( active ) {
			// When a file is uploaded, disable the URL input (file takes priority).
			if ( urlInput ) {
				urlInput.disabled = active;
				if ( active ) {
					urlInput.value = '';
				}
			}
		}

		function resetDropzone() {
			if ( preview ) {
				preview.hidden = true;
			}
			if ( prompt ) {
				prompt.hidden = false;
			}
			if ( fileInput ) {
				fileInput.value = '';
			}
			if ( fileHidden ) {
				fileHidden.value = '';
			}
			setFileMode( false );
			clearError();
		}

		function validateFile( file ) {
			var ext = file.name.split( '.' ).pop().toLowerCase();
			if ( 'zip' !== ext ) {
				showError( admin.invalidType );
				return false;
			}
			if ( file.size > MAX_SIZE ) {
				showError( admin.invalidSize );
				return false;
			}
			return true;
		}

		function uploadFile( file ) {
			if ( ! validateFile( file ) ) {
				return;
			}

			clearError();
			showPreview( file.name );
			if ( filenameEl ) {
				filenameEl.textContent = admin.uploading;
			}

			var formData = new FormData();
			formData.append( 'action', 'mwpspfw_upload_package' );
			formData.append( 'nonce', admin.uploadNonce );
			formData.append( 'file', file );

			fetch( admin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( response ) {
					if ( response && response.success ) {
						if ( fileHidden ) {
							fileHidden.value = response.data.file;
						}
						if ( filenameEl ) {
							filenameEl.textContent = response.data.filename || file.name;
						}
						setFileMode( true );
					} else {
						var msg = ( response && response.data && response.data.message ) || admin.uploadError;
						showError( msg );
						resetDropzone();
					}
				} )
				.catch( function () {
					showError( admin.uploadError );
					resetDropzone();
				} );
		}

		// "Select file" button opens the file dialog.
		if ( selectBtn && fileInput ) {
			selectBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				fileInput.click();
			} );
		}

		// File input change.
		if ( fileInput ) {
			fileInput.addEventListener( 'change', function () {
				if ( fileInput.files && fileInput.files.length > 0 ) {
					uploadFile( fileInput.files[ 0 ] );
				}
			} );
		}

		// Remove button resets the zone and re-enables URL mode.
		if ( removeBtn ) {
			removeBtn.addEventListener( 'click', function () {
				resetDropzone();
			} );
		}

		// Drag-and-drop handlers.
		if ( dropzone ) {
			dropzone.addEventListener( 'dragenter', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				dropzone.classList.add( 'mwpspfw-dropzone--dragging' );
			} );

			dropzone.addEventListener( 'dragover', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
			} );

			dropzone.addEventListener( 'dragleave', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				if ( dropzone.contains( e.relatedTarget ) ) {
					return;
				}
				dropzone.classList.remove( 'mwpspfw-dropzone--dragging' );
			} );

			dropzone.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				dropzone.classList.remove( 'mwpspfw-dropzone--dragging' );

				if ( e.dataTransfer.files && e.dataTransfer.files.length > 0 ) {
					uploadFile( e.dataTransfer.files[ 0 ] );
				}
			} );

			// Also allow clicking anywhere on the dropzone to open the file dialog.
			dropzone.addEventListener( 'click', function ( e ) {
				if ( e.target === selectBtn || ( selectBtn && selectBtn.contains( e.target ) ) || e.target === removeBtn || ( removeBtn && removeBtn.contains( e.target ) ) ) {
					return;
				}
				if ( preview && preview.hidden && fileInput ) {
					fileInput.click();
				}
			} );
		}

		// Initialize file mode state on page load.
		if ( fileHidden && fileHidden.value ) {
			setFileMode( true );
		}

		// Save package form.
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			if ( status ) {
				status.textContent = admin.saving;
			}

			var data = serialize( form );

			ajaxPost( data, function ( response ) {
				if ( status ) {
					status.textContent = ( response && response.success )
						? admin.saved
						: ( ( response && response.data && response.data.message ) || admin.saveError );
				}
			} );
		} );
	}

	/* ─── Overview: Debug log viewer ───────────────────────────────────────── */

	function initDebugLog() {
		var viewBtn = $( '#mwpspfw-view-log-btn' );
		var clearBtn = $( '#mwpspfw-clear-log-btn' );
		var logOutput = $( '#mwpspfw-log-output' );
		var logStatus = $( '#mwpspfw-log-status' );

		if ( ! viewBtn || ! clearBtn ) {
			return;
		}

		viewBtn.addEventListener( 'click', function () {
			if ( logStatus ) {
				logStatus.textContent = 'Loading…';
			}

			var data = 'action=mwpspfw_get_log&nonce=' + encodeURIComponent( admin.logNonce ) + '&lines=200';

			ajaxPost( data, function ( response ) {
				if ( logStatus ) {
					logStatus.textContent = '';
				}
				if ( response.success && logOutput ) {
					logOutput.textContent = response.data.content;
					logOutput.hidden = false;
					// Scroll to bottom to show most recent entries.
					logOutput.scrollTop = logOutput.scrollHeight;
				} else if ( logOutput ) {
					logOutput.textContent = ( response.data && response.data.message ) || 'Failed to load log.';
					logOutput.hidden = false;
				}
			} );
		} );

		clearBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( 'Clear the debug log?' ) ) {
				return;
			}

			if ( logStatus ) {
				logStatus.textContent = 'Clearing…';
			}

			var data = 'action=mwpspfw_clear_log&nonce=' + encodeURIComponent( admin.logNonce );

			ajaxPost( data, function ( response ) {
				if ( logStatus ) {
					logStatus.textContent = ( response && response.success )
						? ( response.data && response.data.message ) || 'Cleared.'
						: ( ( response && response.data && response.data.message ) || 'Failed to clear.' );
				}
				if ( response.success && logOutput ) {
					logOutput.textContent = '';
					logOutput.hidden = true;
				}
			} );
		} );
	}

	/* ─── Init ────────────────────────────────────────────────────────────── */

	function init() {
		initTabs();
		initConditionalFields();
		initDirtyTracking();
		initSaveForm();
		initFileReview();
		initCheckButtons();
		initInstallButtons();
		initBulkActions();
		initFleetIntegrity();
		initPackageForm();
		initDebugLog();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
