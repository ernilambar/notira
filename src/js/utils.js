/**
 * Generic utilities.
 */

import Toastle from 'toastle';

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
function copyToClipboard( { text, html } ) {
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
 * Debounces a function so it runs only after a delay has passed since the last call.
 *
 * @param {Function} func Function to debounce.
 * @param {number} delay Delay in milliseconds.
 * @return {Function} Debounced function.
 */
function debounce( func, delay ) {
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
 * Shows a toast notification.
 *
 * @param {string} text Message text.
 * @param {string} [type] Toast type (e.g. 'success', 'error').
 */
function showToast( text, type ) {
	Toastle( {
		text: text,
		duration: 3000,
		type: type,
	} );
}

export { copyToClipboard, debounce, showToast };
