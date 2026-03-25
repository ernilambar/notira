import 'toastle/style.css';
import './css/main.css';

import { copyToClipboard, debounce, showToast } from './js/utils.js';

const config = window.wordishAdmin || {};
const minLength = config.minLength ?? 0;
const maxLength = config.maxLength ?? 0;
const apiUrl = config.apiUrl ?? '';
const nonce = config.nonce ?? '';

const inputEl = document.getElementById( 'wordish-input' );
const countEl = inputEl ? inputEl.parentNode.querySelector( '.wordish-char-current' ) : null;
const generateBtn = document.getElementById( 'wordish-generate' );
const generateSpinner = document.getElementById( 'wordish-generate-spinner' );
const generationMetaEl = document.getElementById( 'wordish-generation-meta' );
const noticeEl = document.getElementById( 'wordish-notice' );
const outputSection = document.getElementById( 'wordish-output-section' );
const outputEl = document.getElementById( 'wordish-output' );
const copyBtn = document.getElementById( 'wordish-copy' );
const i18n = config.i18n || {};
const copiedLabel = i18n.copiedLabel || '';
let inputErrorTimeout = null;

function updateCount() {
	if ( ! inputEl || ! countEl ) return;
	const len = inputEl.value.length;
	countEl.textContent = len;
	if ( len >= maxLength ) {
		countEl.parentNode.classList.add( 'wordish-at-limit' );
	} else {
		countEl.parentNode.classList.remove( 'wordish-at-limit' );
	}
}

function getTone() {
	const select = document.querySelector( 'select[name="wordish_tone"]' );
	return select ? select.value : 'professional';
}

function setOutput( html ) {
	if ( ! outputEl ) return;
	outputEl.innerHTML = html || '';
	outputEl.setAttribute( 'data-html', html ? 'true' : 'false' );
	outputEl.setAttribute( 'data-empty', html ? 'false' : 'true' );
}

function setNotice( message, type ) {
	if ( ! noticeEl ) {
		return;
	}
	if ( ! message ) {
		noticeEl.textContent = '';
		noticeEl.className = 'wordish-notice wordish-notice--hidden';
		noticeEl.setAttribute( 'aria-hidden', 'true' );
		noticeEl.setAttribute( 'role', 'status' );
		return;
	}
	noticeEl.textContent = message;
	const kind = type === 'error' ? 'error' : 'success';
	noticeEl.className = `wordish-notice wordish-notice--${ kind }`;
	noticeEl.setAttribute( 'aria-hidden', 'false' );
	noticeEl.setAttribute( 'role', type === 'error' ? 'alert' : 'status' );
}

function setGenerationMeta( meta ) {
	if ( ! generationMetaEl ) return;
	generationMetaEl.textContent = '';
	generationMetaEl.classList.add( 'is-empty' );

	if ( ! meta || typeof meta !== 'object' ) {
		return;
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
			rows.push( {
				type: 'plain',
				text: `${ i18n.metaProvider || 'Provider' }: ${ parts.join( ' · ' ) }`,
			} );
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
			rows.push( {
				type: 'plain',
				text: `${ i18n.metaModel || 'Model' }: ${ modelLine }`,
			} );
		}
	}

	if ( tu && typeof tu === 'object' ) {
		const parts = [];
		if ( typeof tu.promptTokens === 'number' ) {
			parts.push( `${ i18n.metaPrompt || 'Prompt' }: ${ tu.promptTokens }` );
		}
		if ( typeof tu.completionTokens === 'number' ) {
			parts.push( `${ i18n.metaCompletion || 'Completion' }: ${ tu.completionTokens }` );
		}
		if ( typeof tu.totalTokens === 'number' ) {
			parts.push( `${ i18n.metaTotal || 'Total' }: ${ tu.totalTokens }` );
		}
		if ( typeof tu.thoughtTokens === 'number' ) {
			parts.push( `${ i18n.metaThought || 'Thought' }: ${ tu.thoughtTokens }` );
		}
		if ( parts.length ) {
			rows.push( {
				type: 'plain',
				text: `${ i18n.metaTokens || 'Tokens' }: ${ parts.join( ' · ' ) }`,
			} );
		}
	}

	const costRaw = meta.estimated_cost_usd;
	const costNum =
		typeof costRaw === 'number'
			? costRaw
			: typeof costRaw === 'string' && costRaw !== ''
			? Number( costRaw )
			: NaN;
	if ( Number.isFinite( costNum ) && costNum >= 0 ) {
		const formatted = new Intl.NumberFormat( undefined, {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 6,
		} ).format( costNum );
		const tpl = i18n.metaEstimatedCostTpl || 'Est. Cost: ≈ %s';
		const usdLine = tpl.replace( '%s', formatted );
		const nprRaw = meta.estimated_cost_npr;
		const nprNum =
			typeof nprRaw === 'number'
				? nprRaw
				: typeof nprRaw === 'string' && nprRaw !== ''
				? Number( nprRaw )
				: NaN;
		let nprFormatted = '';
		if ( Number.isFinite( nprNum ) && nprNum >= 0 ) {
			const nprAmount = new Intl.NumberFormat( undefined, {
				minimumFractionDigits: 0,
				maximumFractionDigits: 4,
			} ).format( nprNum );
			const nprPrefix = ( i18n.metaNprPrefix || 'Rs.' ).trim();
			nprFormatted = nprPrefix ? `${ nprPrefix } ${ nprAmount }` : nprAmount;
		}
		rows.push( { type: 'cost', usdLine, nprFormatted } );
	}

	if ( fromCache && rows.length === 0 ) {
		rows.push( { type: 'plain', text: i18n.metaFromCache || '' } );
	} else if ( fromCache && rows.length > 0 ) {
		rows.push( { type: 'plain', text: i18n.metaFromCache || '' } );
	}

	const filtered = rows.filter( ( r ) => r.type === 'cost' || ( r.type === 'plain' && r.text ) );
	if ( ! filtered.length ) {
		return;
	}

	generationMetaEl.classList.remove( 'is-empty' );
	const frag = document.createDocumentFragment();
	filtered.forEach( ( row ) => {
		const lineEl = document.createElement( 'div' );
		lineEl.className = 'wordish-generation-meta-line';
		if ( row.type === 'cost' ) {
			lineEl.classList.add( 'wordish-generation-meta-line--cost' );
			lineEl.appendChild( document.createTextNode( row.usdLine ) );
			if ( row.nprFormatted ) {
				const nprEl = document.createElement( 'span' );
				nprEl.className = 'wordish-meta-cost-npr';
				nprEl.textContent = ` (${ row.nprFormatted })`;
				lineEl.appendChild( nprEl );
			}
		} else {
			lineEl.textContent = row.text;
		}
		frag.appendChild( lineEl );
	} );
	generationMetaEl.appendChild( frag );
}

function handleCopyClick() {
	if ( ! outputEl ) return;
	if ( outputEl.getAttribute( 'data-empty' ) === 'true' ) {
		showToast( i18n.nothingToCopy || '', 'error' );
		return;
	}
	const html = outputEl.innerHTML || '';
	const text = outputEl.innerText || outputEl.textContent || '';

	copyToClipboard( { text, html } )
		.then( () => {
			showToast( copiedLabel, 'success' );
		} )
		.catch( ( err ) => {
			const msg =
				err?.code === 'NOTHING_TO_COPY' ? i18n.nothingToCopy : i18n.copyFailedManual;
			showToast( msg || '', 'error' );
		} );
}

function generate() {
	const raw = inputEl ? inputEl.value.trim() : '';
	if ( ! raw ) {
		if ( inputErrorTimeout ) clearTimeout( inputErrorTimeout );
		if ( inputEl ) inputEl.focus();
		if ( countEl ) {
			const charCountEl = countEl.parentNode;
			if ( charCountEl ) {
				charCountEl.classList.add( 'wordish-char-count-error' );
				inputErrorTimeout = setTimeout( () => {
					charCountEl.classList.remove( 'wordish-char-count-error' );
					inputErrorTimeout = null;
				}, 3000 );
			}
		}
		return;
	}
	if ( raw.length < minLength ) {
		setNotice( i18n.inputTooShort || '', 'error' );
		return;
	}
	if ( raw.length > maxLength ) {
		setNotice( i18n.textTooLong || '', 'error' );
		return;
	}
	setNotice();
	if ( generateSpinner ) generateSpinner.classList.add( 'is-active' );
	setGenerationMeta( null );

	const body = JSON.stringify( { input: raw, tone: getTone() } );
	const headers = {
		'Content-Type': 'application/json',
		'X-WP-Nonce': nonce,
	};

	fetch( apiUrl, {
		method: 'POST',
		headers,
		body,
		credentials: 'same-origin',
	} )
		.then( ( res ) =>
			res.json().then( ( data ) => ( {
				ok: res.ok,
				status: res.status,
				data,
			} ) )
		)
		.then( ( result ) => {
			if ( result.ok && result.data?.data?.output ) {
				setOutput( result.data.data.output );
				setGenerationMeta( result.data.data.meta );
				setNotice( i18n.generatedSuccess || '', 'success' );
			} else {
				setGenerationMeta( null );
				const msg =
					result.data?.message ||
					result.data?.code ||
					i18n.requestFailed ||
					i18n.somethingWentWrong;
				setNotice( msg || '', 'error' );
			}
		} )
		.catch( () => {
			setGenerationMeta( null );
			setNotice( i18n.networkError || '', 'error' );
		} )
		.finally( () => {
			if ( generateSpinner ) generateSpinner.classList.remove( 'is-active' );
		} );
}

if ( inputEl ) {
	inputEl.addEventListener( 'input', updateCount );
	inputEl.addEventListener( 'paste', () => setTimeout( updateCount, 0 ) );
	updateCount();
}

if ( generateBtn ) {
	generateBtn.addEventListener( 'click', debounce( generate, 300 ) );
}

if ( copyBtn ) {
	copyBtn.addEventListener( 'click', debounce( handleCopyClick, 300 ) );
}
