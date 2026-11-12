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

	$( '.wtb-stores__add' ).on( 'click', function () {
		var html = $( '#wtb-store-template' ).html().replace( /__i__/g, 'n' + next++ );
		var $row = $( html ).appendTo( $rows );
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
} )( jQuery );
