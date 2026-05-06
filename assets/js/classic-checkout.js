/* global ibantestCheckout */
jQuery( function ( $ ) {
	if ( typeof ibantestCheckout === 'undefined' ) {
		return;
	}

	const state = {
		timer: null,
		request: null,
		lastIban: '',
		status: 'idle',
	};
	const validationTrigger = [ 'debounce', 'focusout', 'submit' ].includes( ibantestCheckout.validationTrigger )
		? ibantestCheckout.validationTrigger
		: 'debounce';
	const validationDelay = Math.max( 0, parseInt( ibantestCheckout.validationDelay, 10 ) || 0 );

	const normalizeIban = ( value ) => value.replace( /[^a-zA-Z0-9]/g, '' ).toUpperCase();
	const formatIban = ( value ) => normalizeIban( value ).replace( /(.{4})/g, '$1 ' ).trim();
	const holderFromBilling = () => {
		const company = String( $( '#billing_company' ).val() || '' ).trim();
		const firstName = String( $( '#billing_first_name' ).val() || '' ).trim();
		const lastName = String( $( '#billing_last_name' ).val() || '' ).trim();

		return company || `${ firstName } ${ lastName }`.trim();
	};

	const setStatus = ( type, message ) => {
		state.status = type;
		$( '#ibantest-account-status' )
			.removeClass( 'is-valid is-invalid is-loading is-neutral' )
			.addClass( `is-${ type }` )
			.text( message )
			.prop( 'hidden', false );
	};

	const clearStatus = () => {
		state.status = 'idle';
		$( '#ibantest-account-status' ).text( '' ).prop( 'hidden', true );
		$( '#ibantest-bank-details' ).empty().prop( 'hidden', true );
		$( '#ibantest-iban-validated' ).val( '' );
		$( '#ibantest-account-bic' ).val( '' );
		$( '#ibantest-account-bank' ).val( '' );
	};

	const clearValidatedIban = () => {
		$( '#ibantest-iban-validated' ).val( '' );
		$( '#ibantest-account-bic' ).val( '' );
		$( '#ibantest-account-bank' ).val( '' );
		$( '#ibantest-bank-details' ).empty().prop( 'hidden', true );
	};

	const renderBankDetails = ( data ) => {
		const details = [];
		const bankName = data.bankNameShort || data.bankName;

		if ( bankName ) {
			details.push( bankName );
		}
		if ( data.bic ) {
			details.push( `${ ibantestCheckout.bicText || 'BIC' }: ${ data.bic }` );
		}
		if ( data.bankCity ) {
			details.push( data.bankCity );
		}

		if ( ! details.length ) {
			$( '#ibantest-bank-details' ).empty().prop( 'hidden', true );
			return;
		}

		$( '#ibantest-bank-details' )
			.html( details.map( ( detail ) => `<span>${ $( '<div>' ).text( detail ).html() }</span>` ).join( '' ) )
			.prop( 'hidden', false );
	};

	const applyValidationResult = ( iban, data ) => {
		if ( data.bic ) {
			$( '#ibantest-account-bic' ).val( data.bic );
		}
		$( '#ibantest-account-bank' ).val( data.bankName || data.bankNameShort || '' );
		$( '#ibantest-account-holder' ).val( holderFromBilling() );

		renderBankDetails( data );

		if ( data.valid ) {
			$( '#ibantest-iban-validated' ).val( iban );
			setStatus( 'valid', ibantestCheckout.validText || 'IBAN valid.' );
			return;
		}

		$( '#ibantest-iban-validated' ).val( '' );
		setStatus( 'invalid', data.message || ibantestCheckout.validationErrorText );
	};

	const validateIban = function () {
		window.clearTimeout( state.timer );
		state.timer = null;

		const iban = normalizeIban( $( '#ibantest-account-iban' ).val() );
		if ( ! iban ) {
			clearStatus();
			return;
		}

		if ( iban.length < 12 ) {
			$( '#ibantest-iban-validated' ).val( '' );
			setStatus( 'neutral', ibantestCheckout.enterIbanText || 'Enter your IBAN.' );
			return;
		}

		if ( state.request ) {
			state.request.abort();
		}

		state.lastIban = iban;
		$( '#ibantest-iban-validated' ).val( '' );
		setStatus( 'loading', ibantestCheckout.checkingText || 'Checking IBAN...' );

		const request = $.ajax( {
			type: 'POST',
			url: ibantestCheckout.validateIbanUrl,
			data: {
				iban,
				nonce: ibantestCheckout.validateIbanNonce,
			},
			dataType: 'json',
		} );
		state.request = request;

		request.done( function ( response ) {
			const data = response && response.success ? response.data : {};

			if ( state.lastIban !== iban ) {
				return;
			}

			applyValidationResult( iban, data );
		} ).fail( function ( xhr, status ) {
			if ( 'abort' === status ) {
				return;
			}

			$( '#ibantest-iban-validated' ).val( '' );
			setStatus( 'invalid', ibantestCheckout.errorText || ibantestCheckout.validationErrorText );
		} ).always( function () {
			if ( state.request === request ) {
				state.request = null;
			}
		} );
	};

	const scheduleValidation = function () {
		const formatted = formatIban( $( this ).val() );
		$( this ).val( formatted );
		clearValidatedIban();

		window.clearTimeout( state.timer );
		state.timer = null;

		if ( 'debounce' === validationTrigger ) {
			state.timer = window.setTimeout( validateIban, validationDelay );
		}

		if ( 'submit' === validationTrigger ) {
			clearStatus();
		}
	};

	const showMandate = function () {
		$( '#ibantest-account-holder' ).val( holderFromBilling() );

		$.ajax( {
			type: 'POST',
			url: ibantestCheckout.showMandateUrl,
			data: {
				checkout: $( 'form.checkout' ).serialize(),
				nonce: ibantestCheckout.showMandateNonce,
			},
			dataType: 'json',
		} ).done( function ( response ) {
			if ( response && response.success && response.data && response.data.html ) {
				$( '#ibantest-mandate-preview' ).html( response.data.html ).show();
			}
		} );
	};

	const canSubmit = () => {
		if ( 'submit' === validationTrigger ) {
			return true;
		}

		const iban = normalizeIban( $( '#ibantest-account-iban' ).val() );
		return Boolean( iban && $( '#ibantest-iban-validated' ).val() === iban && 'valid' === state.status );
	};

	$( document.body )
		.on( 'input', '#ibantest-account-iban', scheduleValidation )
		.on( 'focusout', '#ibantest-account-iban', function () {
			if ( 'focusout' === validationTrigger ) {
				validateIban();
			}
		} )
		.on( 'change input', '#billing_first_name, #billing_last_name, #billing_company', function () {
			$( '#ibantest-account-holder' ).val( holderFromBilling() );
		} )
		.on( 'click', '#ibantest-show-mandate', function ( event ) {
			event.preventDefault();
			showMandate();
		} )
		.on( 'checkout_place_order_ibantest', function () {
			if ( canSubmit() ) {
				return true;
			}

			if ( state.timer ) {
				validateIban();
				setStatus( 'loading', ibantestCheckout.waitText || 'Please wait until the IBAN check is complete.' );
			} else if ( 'loading' === state.status ) {
				setStatus( 'loading', ibantestCheckout.waitText || 'Please wait until the IBAN check is complete.' );
			} else {
				setStatus( 'invalid', ibantestCheckout.validationErrorText );
			}

			return false;
		} );
} );
