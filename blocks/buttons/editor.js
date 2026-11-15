/* Store buttons block editor: a stand-in, since the buttons depend on the item being shown. */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;

	wp.blocks.registerBlockType( 'where-to-buy/buttons', {
		edit: function ( props ) {
			var a = props.attributes;
			return el(
				'div',
				useBlockProps( { className: 'wtb-box' } ),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Show', 'where-to-buy' ) },
						el( ToggleControl, {
							label: __( 'Heading', 'where-to-buy' ),
							checked: a.showHeading,
							onChange: function ( v ) { props.setAttributes( { showHeading: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Disclosure notes', 'where-to-buy' ),
							help: __( 'Turn off in lists that show the disclosure once at the top.', 'where-to-buy' ),
							checked: a.showNotes,
							onChange: function ( v ) { props.setAttributes( { showNotes: v } ); },
						} )
					)
				),
				a.showHeading ? el( 'p', { className: 'wtb-box__heading' }, __( 'Where to buy', 'where-to-buy' ) ) : null,
				el(
					'ul',
					{ className: 'wtb-box__stores' },
					el( 'li', null, el( 'span', { className: 'wtb-store' }, __( 'Store', 'where-to-buy' ) ) ),
					el( 'li', null, el( 'span', { className: 'wtb-store' }, __( 'Store', 'where-to-buy' ) ) )
				),
				a.showNotes ? el( 'p', { className: 'wtb-box__note' }, __( 'Disclosure notes', 'where-to-buy' ) ) : null
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
