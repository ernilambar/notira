/**
 * Generic utilities for the Notira admin UI.
 */

/**
 * Debounces a function so it runs only after a delay has passed since the last call.
 *
 * @param {Function} func Function to debounce.
 * @param {number} delay Delay in milliseconds.
 * @return {Function} Debounced function.
 */
export function debounce( func, delay ) {
	let inDebounce;
	return function ( ...args ) {
		const context = this;
		const event = args[ 0 ];

		if ( event && typeof event.preventDefault === 'function' ) {
			event.preventDefault();
		}

		clearTimeout( inDebounce );
		inDebounce = setTimeout( () => func.apply( context, args ), delay );
	};
}

/**
 * Fallback copy using execCommand (synchronous). Throws on failure.
 *
 * @param {string} text Text to copy.
 */
function copyTextFallback( text ) {
	const ta = document.createElement( 'textarea' );
	ta.value = text;
	ta.setAttribute( 'readonly', '' );
	ta.style.position = 'fixed';
	ta.style.left = '-9999px';
	document.body.appendChild( ta );
	ta.select();
	try {
		if ( ! document.execCommand( 'copy' ) ) {
			throw new Error( 'Copy failed.' );
		}
	} finally {
		document.body.removeChild( ta );
	}
}

/**
 * Copy text (and optionally HTML) to the clipboard.
 *
 * @param {Object} options
 * @param {string} options.text Plain text to copy.
 * @param {string} [options.html] Optional HTML to copy (used with text for rich paste).
 * @return {Promise<void>} Resolves when copy succeeded, rejects on failure.
 */
export function copyToClipboard( { text, html } ) {
	text = text || '';
	if ( ! text.trim() ) {
		const err = new Error();
		err.code = 'NOTHING_TO_COPY';
		return Promise.reject( err );
	}

	if ( navigator.clipboard?.write && html != null && html !== '' ) {
		const blobHtml = new Blob( [ html ], { type: 'text/html' } );
		const blobText = new Blob( [ text ], { type: 'text/plain' } );
		return navigator.clipboard
			.write( [
				new window.ClipboardItem( {
					'text/html': blobHtml,
					'text/plain': blobText,
				} ),
			] )
			.catch( () => copyTextFallback( text ) );
	}

	if ( navigator.clipboard?.writeText ) {
		return navigator.clipboard.writeText( text ).catch( () => copyTextFallback( text ) );
	}

	try {
		copyTextFallback( text );
		return Promise.resolve();
	} catch ( e ) {
		return Promise.reject( e );
	}
}

/**
 * Build human-readable generation meta lines from API meta payload.
 *
 * @param {Record<string, unknown>|null|undefined} meta Meta object from API.
 * @param {Record<string, string>} i18n Localized labels from PHP.
 * @return {string[]} Non-empty lines to display.
 */
export function buildGenerationMetaLines( meta, i18n ) {
	if ( ! meta || typeof meta !== 'object' ) {
		return [];
	}

	const rows = [];
	const provider = meta.provider;
	const model = meta.model;
	const tu = meta.token_usage;
	const fromCache = Boolean( meta.from_cache );

	if ( provider && typeof provider === 'object' ) {
		const name = typeof provider.name === 'string' ? provider.name : '';
		const id = typeof provider.id === 'string' ? provider.id : '';
		const type = typeof provider.type === 'string' ? provider.type : '';
		const parts = [];
		if ( name ) {
			parts.push( name );
		} else if ( id ) {
			parts.push( id );
		}
		if ( type && type !== id ) {
			parts.push( type );
		}
		if ( parts.length ) {
			const label = i18n.metaProvider || '';
			rows.push( `${ label }: ${ parts.join( ' · ' ) }` );
		}
	}

	if ( model && typeof model === 'object' ) {
		const name = typeof model.name === 'string' ? model.name : '';
		const id = typeof model.id === 'string' ? model.id : '';
		let modelLine = '';
		if ( name && id && name !== id ) {
			modelLine = `${ name } (${ id })`;
		} else if ( name || id ) {
			modelLine = name || id;
		}
		if ( modelLine ) {
			const label = i18n.metaModel || '';
			rows.push( `${ label }: ${ modelLine }` );
		}
	}

	if ( tu && typeof tu === 'object' ) {
		const parts = [];
		if ( typeof tu.promptTokens === 'number' ) {
			parts.push( `${ i18n.metaPrompt || '' }: ${ tu.promptTokens }` );
		}
		if ( typeof tu.completionTokens === 'number' ) {
			parts.push( `${ i18n.metaCompletion || '' }: ${ tu.completionTokens }` );
		}
		if ( typeof tu.totalTokens === 'number' ) {
			parts.push( `${ i18n.metaTotal || '' }: ${ tu.totalTokens }` );
		}
		if ( typeof tu.thoughtTokens === 'number' ) {
			parts.push( `${ i18n.metaThought || '' }: ${ tu.thoughtTokens }` );
		}
		if ( parts.length ) {
			const label = i18n.metaTokens || '';
			rows.push( `${ label }: ${ parts.join( ' · ' ) }` );
		}
	}

	if ( fromCache ) {
		const cacheLine = i18n.metaFromCache || '';
		if ( cacheLine ) {
			rows.push( cacheLine );
		}
	}

	return rows.filter( ( line ) => typeof line === 'string' && line.length > 0 );
}
