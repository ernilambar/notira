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
const outputSection = document.getElementById( 'wordish-output-section' );
const outputEl = document.getElementById( 'wordish-output' );
const copyBtn = document.getElementById( 'wordish-copy' );
const i18n = config.i18n || {};
const copyLabel = i18n.copyLabel || '';
const copiedLabel = i18n.copiedLabel || '';
let copyBtnRestoreTimeout = null;

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

function setCopyButtonCopied() {
	if ( ! copyBtn ) return;
	if ( copyBtnRestoreTimeout ) clearTimeout( copyBtnRestoreTimeout );
	copyBtn.textContent = copiedLabel;
	copyBtnRestoreTimeout = setTimeout( () => {
		copyBtn.textContent = copyLabel;
		copyBtnRestoreTimeout = null;
	}, 3000 );
}

function handleCopyClick() {
	if ( ! outputEl ) return;
	const html = outputEl.innerHTML || '';
	const text = outputEl.innerText || outputEl.textContent || '';

	copyToClipboard( { text, html } )
		.then( () => {
			setCopyButtonCopied();
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
		showToast( i18n.pleaseEnterText || '', 'error' );
		return;
	}
	if ( raw.length < minLength ) {
		showToast( i18n.inputTooShort || '', 'error' );
		return;
	}
	if ( raw.length > maxLength ) {
		showToast( i18n.textTooLong || '', 'error' );
		return;
	}
	if ( generateBtn ) generateBtn.disabled = true;
	if ( generateSpinner ) generateSpinner.classList.add( 'is-active' );

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
				showToast( i18n.generatedSuccess || '', 'success' );
			} else {
				const msg =
					result.data?.message ||
					result.data?.code ||
					i18n.requestFailed ||
					i18n.somethingWentWrong;
				showToast( msg || '', 'error' );
			}
		} )
		.catch( () => {
			showToast( i18n.networkError || '', 'error' );
		} )
		.finally( () => {
			if ( generateBtn ) generateBtn.disabled = false;
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
