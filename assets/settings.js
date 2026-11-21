/* Where to Buy settings pages: sortable store rows, add/remove, and media-library image pickers. */
( function ( $ ) {
	'use strict';

	var l10n = window.wtbSettings || {};
	var $rows = $( '.wtb-stores__rows' );
	var next = Date.now();

	$rows.sortable( {
		handle: '.wtb-store-row__handle',
		axis: 'y',
		helper: function ( e, tr ) {
			// Keep cell widths while dragging.
			tr.children().each( function () {
				$( this ).width( $( this ).width() );
			} );
			return tr;
		},
	} );

	var catalog = l10n.catalog || [];
	var $picker = $( '.wtb-store-picker' );
	var $add = $( '.wtb-stores__add' );

	function known( id ) {
		for ( var i = 0; i < catalog.length; i++ ) {
			if ( catalog[ i ].id === id ) {
				return catalog[ i ];
			}
		}
		return null;
	}

	// A row's store id: its saved id, else one made from the name (as the server does).
	function rowId( $row ) {
		var id = $.trim( $row.find( 'input[name$="[id]"]' ).val() || '' );
		if ( id ) {
			return id;
		}
		return $.trim( $row.find( '.wtb-store-row__name' ).val() || '' ).toLowerCase().replace( /&/g, '' ).replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
	}

	function togglePicker( open ) {
		if ( open ) {
			var have = {};
			$rows.children( 'tr' ).each( function () {
				have[ rowId( $( this ) ) ] = true;
			} );
			$picker.find( '.wtb-store-picker__store' ).each( function () {
				var $b = $( this );
				var id = String( $b.data( 'store' ) || '' );
				var on = id !== '' && have[ id ];
				$b.prop( 'disabled', !! on ).attr( 'title', on ? l10n.added : null );
			} );
		}
		$picker.prop( 'hidden', ! open );
		$add.attr( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			$picker.find( '.wtb-store-picker__store:enabled' ).first().trigger( 'focus' );
		}
	}

	$add.on( 'click', function () {
		togglePicker( $picker.prop( 'hidden' ) );
	} );

	$picker.on( 'click', '.wtb-store-picker__cancel', function () {
		togglePicker( false );
		$add.trigger( 'focus' );
	} );

	$picker.on( 'click', '.wtb-store-picker__store', function () {
		var store = known( String( $( this ).data( 'store' ) || '' ) );
		var html = $( '#wtb-store-template' ).html().replace( /__i__/g, 'n' + next++ );
		var $row = $( html ).appendTo( $rows );
		togglePicker( false );
		if ( ! store ) {
			$row.find( '.wtb-store-row__name' ).trigger( 'focus' );
			return;
		}
		$row.find( 'input[name$="[id]"]' ).val( store.id );
		$row.find( '.wtb-store-row__name' ).val( store.name );
		$row.find( 'input[name$="[domains]"]' ).val( store.domains );
		$row.find( 'input[name$="[tag_param]"]' ).val( store.tag_param );
		$row.find( 'input[name$="[note]"]' ).val( store.note );
		if ( store.tag_param || store.note ) {
			$row.find( '.wtb-store-row__more' ).prop( 'open', true );
		}
		$row.find( '.wtb-store-row__name' ).trigger( 'focus' );
	} );

	$rows.on( 'click', '.wtb-store-row__remove', function () {
		var $row = $( this ).closest( 'tr' );
		var named = $.trim( $row.find( '.wtb-store-row__name' ).val() ) !== '';
		if ( named && ! window.confirm( l10n.confirmDrop ) ) {
			return;
		}
		$row.remove();
	} );

	$( document ).on( 'click', '.wtb-image__choose', function ( e ) {
		e.preventDefault();
		var $box = $( this ).closest( '.wtb-image' );
		var frame = wp.media( {
			title: l10n.chooseImage,
			button: { text: l10n.useImage },
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			var src = ( att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url );
			$box.find( '.wtb-image__id' ).val( att.id );
			$box.find( '.wtb-image__preview' ).empty().append( $( '<img alt="">' ).attr( 'src', src ) );
			$box.find( '.wtb-image__remove' ).prop( 'hidden', false );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.wtb-image__remove', function ( e ) {
		e.preventDefault();
		var $box = $( this ).closest( '.wtb-image' );
		$box.find( '.wtb-image__id' ).val( '0' );
		$box.find( '.wtb-image__preview' ).html( $box.find( '.wtb-image__empty-tpl' ).html() );
		$( this ).prop( 'hidden', true );
	} );

	// Upload one image file into the Media Library; resolves with the attachment JSON.
	function upload( file ) {
		var data = new window.FormData();
		data.append( 'file', file, file.name );
		return $.ajax( {
			url: l10n.mediaUrl,
			method: 'POST',
			data: data,
			processData: false,
			contentType: false,
			headers: { 'X-WP-Nonce': l10n.nonce },
		} );
	}

	function setImage( $box, att ) {
		var sizes = att.media_details && att.media_details.sizes;
		var src = sizes && sizes.medium ? sizes.medium.source_url : att.source_url;
		$box.find( '.wtb-image__id' ).val( att.id );
		$box.find( '.wtb-image__preview' ).empty().append( $( '<img alt="">' ).attr( 'src', src ) );
		$box.find( '.wtb-image__remove' ).prop( 'hidden', false );
	}

	function failText( file, xhr ) {
		var msg = ( l10n.uploadFail || '%s' ).replace( '%s', file.name );
		var why = xhr && xhr.responseJSON && xhr.responseJSON.message;
		return why ? msg + ' ' + $( '<div>' ).html( why ).text() : msg;
	}

	$( document ).on( 'click', '.wtb-image__upload', function ( e ) {
		e.preventDefault();
		$( this ).closest( '.wtb-image' ).find( '.wtb-image__file' ).trigger( 'click' );
	} );

	$( document ).on( 'change', '.wtb-image__file', function () {
		var input = this;
		var file = input.files && input.files[ 0 ];
		var $box = $( input ).closest( '.wtb-image' );
		var $status = $box.find( '.wtb-image__status' );
		if ( ! file ) {
			return;
		}
		$status.text( l10n.uploading );
		upload( file )
			.done( function ( att ) {
				setImage( $box, att );
				$status.text( '' );
			} )
			.fail( function ( xhr ) {
				$status.text( failText( file, xhr ) );
			} )
			.always( function () {
				input.value = '';
			} );
	} );

	// Several logos at once: each file goes to the store it's named after. amazon.png and
	// amazon-icon.png both match Amazon; the store's usual logo wins, then the shortest name.
	$( '.wtb-logos__upload' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '.wtb-logos__file' ).trigger( 'click' );
	} );

	$( '.wtb-logos__file' ).on( 'change', function () {
		var input = this;
		var files = Array.prototype.slice.call( input.files || [] );
		var $status = $( '.wtb-logos__status' );
		var picks = [];
		var used = {};
		var fits = {};
		if ( ! files.length ) {
			return;
		}
		$rows.children( 'tr' ).each( function () {
			var $row = $( this );
			var id = rowId( $row );
			var store = known( id );
			var best = null;
			var bestScore = -1;
			if ( ! id ) {
				return;
			}
			files.forEach( function ( file ) {
				var base = file.name.toLowerCase().replace( /\.[a-z0-9]+$/, '' );
				var score = -1;
				if ( store && store.logo && base === store.logo ) {
					score = 1000;
				} else if ( base === id ) {
					score = 900;
				} else if ( base.indexOf( id + '-' ) === 0 && base.indexOf( 'white' ) === -1 ) {
					score = 500 - base.length;
				} else if ( base.indexOf( id + '-' ) === 0 ) {
					score = 100 - base.length;
				}
				if ( score >= 0 ) {
					fits[ file.name ] = true;
				}
				if ( score > bestScore ) {
					best = file;
					bestScore = score;
				}
			} );
			if ( best ) {
				picks.push( { $row: $row, file: best } );
				used[ best.name ] = true;
			}
		} );
		var others = files.filter( function ( f ) {
			return ! used[ f.name ] && fits[ f.name ];
		} ).length;
		var skipped = files.filter( function ( f ) {
			return ! fits[ f.name ];
		} ).map( function ( f ) {
			return f.name;
		} );
		input.value = '';
		if ( ! picks.length ) {
			$status.text( l10n.logosNone );
			return;
		}
		$status.text( l10n.uploading );
		var done = [];
		var errors = [];
		var jobs = picks.map( function ( p ) {
			return upload( p.file )
				.done( function ( att ) {
					setImage( p.$row.find( '.wtb-image' ), att );
					done.push( $.trim( p.$row.find( '.wtb-store-row__name' ).val() ) );
				} )
				.fail( function ( xhr ) {
					errors.push( failText( p.file, xhr ) );
				} )
				.then( null, function () {
					return $.Deferred().resolve();
				} );
		} );
		$.when.apply( $, jobs ).always( function () {
			var parts = [];
			if ( done.length ) {
				parts.push( l10n.logosAdded.replace( '%s', done.join( ', ' ) ) );
			}
			parts = parts.concat( errors );
			if ( others ) {
				parts.push( l10n.logosOthers.replace( '%d', others ) );
			}
			if ( skipped.length ) {
				parts.push( l10n.logosSkipped.replace( '%s', skipped.join( ', ' ) ) );
			}
			$status.text( parts.join( ' ' ) );
		} );
	} );
} )( jQuery );
