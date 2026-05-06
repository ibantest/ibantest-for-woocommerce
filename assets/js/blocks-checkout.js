( function () {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { getSetting } = window.wc.wcSettings;
	const { createElement, useEffect, useState } = window.wp.element;
	const { decodeEntities } = window.wp.htmlEntities;
	const { __ } = window.wp.i18n;

	const settings = getSetting( 'ibantest_data', {} );
	const label = decodeEntities( settings.title || 'SEPA Direct Debit' );
	const validationTrigger = [ 'debounce', 'focusout', 'submit' ].includes( settings.validationTrigger )
		? settings.validationTrigger
		: 'debounce';
	const validationDelay = Math.max( 0, parseInt( settings.validationDelay, 10 ) || 0 );

	const normalizeIban = ( value ) => value.replace( /[^a-zA-Z0-9]/g, '' ).toUpperCase();
	const formatIban = ( value ) => normalizeIban( value ).replace( /(.{4})/g, '$1 ' ).trim();

	const field = ( id, labelText, value, setValue, required, onBlur ) =>
		createElement(
			'p',
			{ className: 'ibantest-block-field' },
			createElement( 'label', { htmlFor: id }, labelText + ( required ? ' *' : '' ) ),
			createElement( 'input', {
				id,
				type: 'text',
				value,
				required,
				autoComplete: 'off',
				inputMode: 'text',
				onChange: ( event ) => setValue( formatIban( event.target.value ) ),
				onBlur,
			} ),
			createElement(
				'span',
				{ className: 'description' },
				settings.ibanHelpText || __( 'Enter your IBAN, for example DE02 6005 0101 0002 0343 04.', 'ibantest-for-woocommerce' )
			)
		);

	const Content = ( props ) => {
		const [ iban, setIban ] = useState( '' );
		const [ bic, setBic ] = useState( '' );
		const [ mandateAccepted, setMandateAccepted ] = useState( false );
		const [ mandateHtml, setMandateHtml ] = useState( '' );
		const [ status, setStatus ] = useState( 'idle' );
		const [ message, setMessage ] = useState( '' );
		const [ bankData, setBankData ] = useState( {} );
		const [ validatedIban, setValidatedIban ] = useState( '' );
		const { eventRegistration, emitResponse } = props;
		const onPaymentSetup = eventRegistration ? eventRegistration.onPaymentSetup : null;
		const billingData = props.billing && props.billing.billingData ? props.billing.billingData : {};
		const holder = billingData.company || `${ billingData.first_name || '' } ${ billingData.last_name || '' }`.trim();

		const applyValidationData = ( normalizedIban, data ) => {
			if ( data.bic ) {
				setBic( data.bic );
			}
			setBankData( data );

			if ( data.valid ) {
				setValidatedIban( normalizedIban );
				setStatus( 'valid' );
				setMessage( settings.validText || __( 'IBAN valid.', 'ibantest-for-woocommerce' ) );
				return;
			}

			setStatus( 'invalid' );
			setMessage( data.message || settings.validationErrorText || __( 'Please check your bank account data.', 'ibantest-for-woocommerce' ) );
		};

		const validateCurrentIban = ( signal ) => {
			const normalizedIban = normalizeIban( iban );
			setValidatedIban( '' );
			setBic( '' );
			setBankData( {} );

			if ( ! normalizedIban ) {
				setStatus( 'idle' );
				setMessage( '' );
				return;
			}

			if ( normalizedIban.length < 12 ) {
				setStatus( 'neutral' );
				setMessage( settings.enterIbanText || __( 'Enter your IBAN to start the check.', 'ibantest-for-woocommerce' ) );
				return;
			}

			const formData = new window.FormData();
			formData.append( 'nonce', settings.validateIbanNonce );
			formData.append( 'iban', normalizedIban );

			setStatus( 'loading' );
			setMessage( settings.checkingText || __( 'Checking IBAN...', 'ibantest-for-woocommerce' ) );

			window.fetch( settings.validateIbanUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
				signal,
			} )
				.then( ( response ) => response.json() )
				.then( ( response ) => {
					const data = response && response.success ? response.data || {} : {};
					applyValidationData( normalizedIban, data );
				} )
				.catch( ( error ) => {
					if ( 'AbortError' === error.name ) {
						return;
					}

					setStatus( 'invalid' );
					setMessage( settings.errorText || settings.validationErrorText || __( 'Please check your bank account data.', 'ibantest-for-woocommerce' ) );
				} );
		};

		useEffect( () => {
			const normalizedIban = normalizeIban( iban );

			setValidatedIban( '' );
			setBic( '' );
			setBankData( {} );

			if ( ! normalizedIban ) {
				setStatus( 'idle' );
				setMessage( '' );
				return undefined;
			}

			if ( 'submit' === validationTrigger ) {
				setStatus( 'idle' );
				setMessage( '' );
				return undefined;
			}

			if ( normalizedIban.length < 12 ) {
				setStatus( 'neutral' );
				setMessage( settings.enterIbanText || __( 'Enter your IBAN to start the check.', 'ibantest-for-woocommerce' ) );
				return undefined;
			}

			if ( 'focusout' === validationTrigger ) {
				setStatus( 'neutral' );
				setMessage( settings.enterIbanText || __( 'Enter your IBAN to start the check.', 'ibantest-for-woocommerce' ) );
				return undefined;
			}

			const controller = new window.AbortController();
			const timer = window.setTimeout( () => {
				validateCurrentIban( controller.signal );
			}, validationDelay );

			return () => {
				window.clearTimeout( timer );
				controller.abort();
			};
		}, [ iban ] );

		useEffect( () => {
			if ( ! onPaymentSetup ) {
				return undefined;
			}

			return onPaymentSetup( () => {
				const normalizedIban = normalizeIban( iban );
				const liveValidationRequired = 'submit' !== validationTrigger;
				if ( ! normalizedIban || ( liveValidationRequired && ( validatedIban !== normalizedIban || 'valid' !== status ) ) || ( settings.mandateRequired && ! mandateAccepted ) ) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: 'loading' === status ? settings.waitText : settings.validationErrorText || __( 'Please check your bank account data.', 'ibantest-for-woocommerce' ),
						messageContext: emitResponse.noticeContexts.PAYMENTS,
					};
				}

				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							payment_method: 'ibantest',
							ibantest_account_iban: normalizedIban,
							ibantest_account_bic: bic,
							ibantest_account_holder: holder,
							ibantest_iban_validated: validatedIban,
							ibantest_account_bank: bankData.bankName || bankData.bankNameShort || '',
							ibantest_mandate_checkbox: mandateAccepted ? '1' : '',
						},
					},
				};
			} );
		}, [ iban, bic, holder, mandateAccepted, status, validatedIban, bankData, onPaymentSetup, emitResponse ] );

		const showMandate = () => {
			const formData = new window.FormData();
			formData.append( 'nonce', settings.showMandateNonce );
			formData.append( 'ibantest_account_iban', normalizeIban( iban ) );
			formData.append( 'ibantest_account_bic', bic );
			formData.append( 'ibantest_account_holder', holder );

			window.fetch( settings.showMandateUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			} )
				.then( ( response ) => response.json() )
				.then( ( response ) => {
					if ( response && response.success && response.data && response.data.html ) {
						setMandateHtml( response.data.html );
					}
				} );
		};

		const bankDetails = [];
		const bankName = bankData.bankNameShort || bankData.bankName;
		if ( bankName ) {
			bankDetails.push( bankName );
		}
		if ( bankData.bic ) {
			bankDetails.push( `${ settings.bicText || 'BIC' }: ${ bankData.bic }` );
		}
		if ( bankData.bankCity ) {
			bankDetails.push( bankData.bankCity );
		}

		return createElement(
			'div',
			{ className: 'ibantest-block-fields' },
			settings.description ? createElement( 'p', null, decodeEntities( settings.description ) ) : null,
			field(
				'ibantest-block-iban',
				settings.ibanLabel || 'IBAN',
				iban,
				setIban,
				true,
				() => {
					if ( 'focusout' === validationTrigger ) {
						validateCurrentIban();
					}
				}
			),
			message
				? createElement(
						'div',
						{ className: `ibantest-validation-status is-${ status }`, role: 'status', 'aria-live': 'polite' },
						message
				  )
				: null,
			bankDetails.length
				? createElement(
						'div',
						{ className: 'ibantest-bank-details' },
						bankDetails.map( ( detail ) => createElement( 'span', { key: detail }, detail ) )
				  )
				: null,
			settings.mandateRequired
				? createElement(
						'label',
						{ className: 'wc-block-components-checkbox' },
						createElement( 'input', {
							type: 'checkbox',
							checked: mandateAccepted,
							onChange: ( event ) => setMandateAccepted( event.target.checked ),
						} ),
						createElement( 'span', null, decodeEntities( settings.mandateCheckboxLabel || '' ) )
				  )
				: null,
			createElement(
				'button',
				{ type: 'button', className: 'button', onClick: showMandate },
				settings.showMandateLabel || __( 'Show SEPA mandate', 'ibantest-for-woocommerce' )
			),
			mandateHtml
				? createElement( 'div', {
						className: 'ibantest-mandate-preview',
						dangerouslySetInnerHTML: { __html: mandateHtml },
				  } )
				: null
		);
	};

	const Label = ( props ) => {
		const { PaymentMethodLabel } = props.components;
		return createElement( PaymentMethodLabel, { text: label } );
	};

	registerPaymentMethod( {
		name: 'ibantest',
		label: createElement( Label, null ),
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		canMakePayment: () => true,
		ariaLabel: label,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
