const providerSel = document.getElementById( 'optiz_preferred_provider' );

if ( providerSel ) {
	function populateModels( providerId, selectedModel ) {
		const modelSel = document.getElementById( 'optiz_preferred_model' );
		if ( ! modelSel ) return;

		modelSel.innerHTML = `<option value="">${ window.notiraSettings.default_label }</option>`;
		if ( ! providerId ) return;

		fetch( window.notiraSettings.ajax_url, {
			method: 'POST',
			body: new URLSearchParams( {
				action: 'notira_get_models',
				nonce: window.notiraSettings.nonce,
				provider: providerId,
			} ),
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( { success, data } ) {
				if ( ! success || ! Array.isArray( data ) ) return;
				data.forEach( function ( { id, name } ) {
					modelSel.add( new Option( name, id, false, id === selectedModel ) );
				} );
			} )
			.catch( function () {} );
	}

	providerSel.addEventListener( 'change', function ( event ) {
		populateModels( event.target.value, '' );
	} );

	if ( providerSel.value ) {
		populateModels( providerSel.value, window.notiraSettings.saved_model );
	}
}
