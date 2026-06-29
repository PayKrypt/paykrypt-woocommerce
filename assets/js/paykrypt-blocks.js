( function () {
	const settings = window.wc.wcSettings.getSetting( 'paykrypt_data', {} );
	const label = window.wp.htmlEntities.decodeEntities( settings.title || 'Crypto via PayKrypt' );
	const description = window.wp.htmlEntities.decodeEntities(
		settings.description || 'Pay securely with cryptocurrency using PayKrypt.'
	);

	const Content = function () {
		return window.wp.element.createElement( 'span', null, description );
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'paykrypt',
		label: label,
		content: window.wp.element.createElement( Content ),
		edit: window.wp.element.createElement( Content ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: label,
		supports: {
			features: settings.supports || [],
		},
	} );
} )();

