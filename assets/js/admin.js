( function () {
	const config = window.ibantestAdmin;
	if ( ! config ) {
		return;
	}

	const refreshButton = document.getElementById( 'ibantest-refresh-credits' );
	const creditsValue = document.getElementById( 'ibantest-remaining-credits' );
	const lastUpdated = document.getElementById( 'ibantest-credits-last-updated' );
	const status = document.getElementById( 'ibantest-credit-status' );
	const onboarding = document.getElementById( 'ibantest-onboarding' );
	const verifyButton = document.getElementById( 'ibantest-verify-api-key' );
	const onboardingApiKey = document.getElementById( 'ibantest-onboarding-api-key' );
	const onboardingStatus = document.getElementById( 'ibantest-onboarding-status' );
	const settingsApiKey = document.getElementById( 'woocommerce_ibantest_apikey' );

	if ( ! refreshButton || ! creditsValue || ! lastUpdated || ! status ) {
		return;
	}

	const setStatus = ( element, message, isError ) => {
		if ( ! element ) {
			return;
		}

		element.hidden = false;
		element.textContent = message;
		element.classList.toggle( 'ibantest-credit-status-error', Boolean( isError ) );
	};

	const postAdminAjax = ( action, nonce, extraBody = {} ) => {
		const body = new window.URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', nonce );

		Object.keys( extraBody ).forEach( ( key ) => body.append( key, extraBody[ key ] ) );

		return window
			.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			} )
			.then( ( response ) => response.json() );
	};

	const updateCreditOverview = ( data ) => {
		creditsValue.textContent = data.creditsLabel;
		creditsValue.classList.remove( 'remaining-credits-unavailable' );
		lastUpdated.textContent = data.lastUpdated;
	};

	refreshButton.addEventListener( 'click', ( event ) => {
		event.preventDefault();

		const previousText = refreshButton.textContent;
		refreshButton.setAttribute( 'aria-disabled', 'true' );
		refreshButton.classList.add( 'disabled' );
		refreshButton.textContent = config.loadingText;
		setStatus( status, config.loadingText, false );

		postAdminAjax( config.refreshAction, config.refreshNonce )
			.then( ( response ) => {
				const data = response && response.data ? response.data : {};

				if ( ! response || ! response.success ) {
					throw new Error( data.message || config.errorText );
				}

				updateCreditOverview( data );
				setStatus( status, data.message, false );
			} )
			.catch( ( error ) => {
				setStatus( status, error.message || config.errorText, true );
			} )
			.finally( () => {
				refreshButton.removeAttribute( 'aria-disabled' );
				refreshButton.classList.remove( 'disabled' );
				refreshButton.textContent = previousText;
			} );
	} );

	if ( verifyButton && onboardingApiKey && onboardingStatus ) {
		verifyButton.addEventListener( 'click', () => {
			const apiKey = onboardingApiKey.value.trim();
			const previousText = verifyButton.textContent;

			verifyButton.disabled = true;
			verifyButton.textContent = config.verifyText;
			setStatus( onboardingStatus, config.verifyText, false );

			postAdminAjax( config.verifyAction, config.verifyNonce, { apiKey } )
				.then( ( response ) => {
					const data = response && response.data ? response.data : {};

					if ( ! response || ! response.success ) {
						throw new Error( data.message || config.errorText );
					}

					updateCreditOverview( data );
					setStatus( onboardingStatus, data.message, false );
					setStatus( status, data.message, false );

					if ( settingsApiKey ) {
						settingsApiKey.value = apiKey;
					}

					if ( onboarding ) {
						onboarding.classList.add( 'ibantest-onboarding-complete' );
					}
				} )
				.catch( ( error ) => {
					setStatus( onboardingStatus, error.message || config.errorText, true );
				} )
				.finally( () => {
					verifyButton.disabled = false;
					verifyButton.textContent = previousText;
				} );
		} );
	}
} )();
