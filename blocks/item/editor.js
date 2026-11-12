/* Item block editor: choose an item, show it as a card or just the buttons. Rendered on the server. */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useSelect = wp.data.useSelect;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var Placeholder = wp.components.Placeholder;
	var Spinner = wp.components.Spinner;
	var ServerSideRender = wp.serverSideRender;

	function ItemSelect( props ) {
		var items = useSelect( function ( select ) {
			return select( 'core' ).getEntityRecords( 'postType', 'wtb_item', {
				per_page: 100,
				status: 'publish',
				orderby: 'title',
				order: 'asc',
				_fields: 'id,title',
			} );
		}, [] );

		if ( ! items ) {
			return el( Spinner );
		}
		var options = [ { value: 0, label: __( '— Choose an item —', 'where-to-buy' ) } ].concat(
			items.map( function ( p ) {
				return { value: p.id, label: ( p.title && p.title.rendered ) || '#' + p.id };
			} )
		);
		return el( SelectControl, {
			label: __( 'Where to Buy', 'where-to-buy' ),
			value: props.value,
			options: options,
			onChange: function ( v ) {
				props.onChange( parseInt( v, 10 ) || 0 );
			},
			__nextHasNoMarginBottom: true,
		} );
	}

	wp.blocks.registerBlockType( 'where-to-buy/item', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps();

			var controls = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Where to Buy', 'where-to-buy' ) },
					el( ItemSelect, { value: a.itemId, onChange: function ( v ) { set( { itemId: v } ); } } ),
					el( SelectControl, {
						label: __( 'Show', 'where-to-buy' ),
						value: a.show,
						options: [
							{ value: 'card', label: __( 'Photo, description and buttons', 'where-to-buy' ) },
							{ value: 'buttons', label: __( 'Just the buttons', 'where-to-buy' ) },
						],
						onChange: function ( v ) { set( { show: v } ); },
						__nextHasNoMarginBottom: true,
					} )
				)
			);

			var body = a.itemId
				? el( ServerSideRender, {
					block: 'where-to-buy/item',
					attributes: a,
					EmptyResponsePlaceholder: function () {
						return el( Placeholder, {
							icon: 'cart',
							label: __( 'Where to Buy', 'where-to-buy' ),
							instructions: __( 'Nothing to show yet: the item isn’t published, or none of its stores has a valid link.', 'where-to-buy' ),
						} );
					},
				} )
				: el(
					Placeholder,
					{ icon: 'cart', label: __( 'Where to Buy', 'where-to-buy' ), instructions: __( 'Choose which item to show.', 'where-to-buy' ) },
					el( ItemSelect, { value: a.itemId, onChange: function ( v ) { set( { itemId: v } ); } } )
				);

			return el( 'div', blockProps, controls, body );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
