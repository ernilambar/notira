/**
 * Wordish admin: character count, generate, copy to clipboard.
 */

(function () {
	'use strict';

	var minLength = wordishAdmin.minLength;
	var maxLength = wordishAdmin.maxLength;
	var apiUrl = wordishAdmin.apiUrl;
	var nonce = wordishAdmin.nonce;

	var inputEl = document.getElementById('wordish-input');
	var countEl = inputEl ? inputEl.parentNode.querySelector('.wordish-char-current') : null;
	var generateBtn = document.getElementById('wordish-generate');
	var generateSpinner = document.getElementById('wordish-generate-spinner');
	var outputSection = document.getElementById('wordish-output-section');
	var outputEl = document.getElementById('wordish-output');
	var copyBtn = document.getElementById('wordish-copy');
	var messageEl = document.getElementById('wordish-message');
	var copyLabel = wordishAdmin.i18n.copyLabel;
	var copiedLabel = wordishAdmin.i18n.copiedLabel;
	var copyBtnRestoreTimeout = null;

	function updateCount() {
		if (!inputEl || !countEl) return;
		var len = inputEl.value.length;
		countEl.textContent = len;
		if (len >= maxLength) {
			countEl.parentNode.classList.add('wordish-at-limit');
		} else {
			countEl.parentNode.classList.remove('wordish-at-limit');
		}
	}

	function getTone() {
		var radio = document.querySelector('input[name="wordish_tone"]:checked');
		return radio ? radio.value : 'professional';
	}

	function showMessage(text, type) {
		if (!messageEl) return;
		messageEl.textContent = text || '';
		messageEl.className = 'wordish-message' + (type ? ' ' + type : '');
	}

	function setOutput(html) {
		if (!outputEl) return;
		outputEl.innerHTML = html || '';
		outputEl.setAttribute('data-html', html ? 'true' : 'false');
		outputEl.setAttribute('data-empty', html ? 'false' : 'true');
	}

	function setCopyButtonCopied() {
		if (!copyBtn) return;
		if (copyBtnRestoreTimeout) clearTimeout(copyBtnRestoreTimeout);
		copyBtn.textContent = copiedLabel;
		copyBtnRestoreTimeout = setTimeout(function () {
			copyBtn.textContent = copyLabel;
			copyBtnRestoreTimeout = null;
		}, 3000);
	}

	function copyToClipboard() {
		if (!outputEl) return;
		var html = outputEl.innerHTML || '';
		var text = outputEl.innerText || outputEl.textContent || '';
		if (!text.trim()) {
			showMessage('Nothing to copy.', 'error');
			return;
		}
		if (navigator.clipboard && navigator.clipboard.write) {
			var blobHtml = new Blob([html], { type: 'text/html' });
			var blobText = new Blob([text], { type: 'text/plain' });
			navigator.clipboard.write([
				new window.ClipboardItem({ 'text/html': blobHtml, 'text/plain': blobText })
			]).then(
				function () {
					showMessage('', '');
					setCopyButtonCopied();
				},
				function () {
					fallbackCopyText(text);
				}
			);
		} else if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(
				function () {
					showMessage('', '');
					setCopyButtonCopied();
				},
				function () {
					fallbackCopyText(text);
				}
			);
		} else {
			fallbackCopyText(text);
		}
	}

	function fallbackCopyText(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'fixed';
		ta.style.left = '-9999px';
		document.body.appendChild(ta);
		ta.select();
		try {
			document.execCommand('copy');
			showMessage('', '');
			setCopyButtonCopied();
		} catch (err) {
			showMessage('Could not copy. Please select and copy manually.', 'error');
		}
		document.body.removeChild(ta);
	}

	function generate() {
		var raw = inputEl ? inputEl.value.trim() : '';
		if (!raw) {
			showMessage('Please enter some text.', 'error');
			return;
		}
		if (raw.length < minLength) {
			showMessage(wordishAdmin.i18n.inputTooShort, 'error');
			return;
		}
		if (raw.length > maxLength) {
			showMessage('Text is too long.', 'error');
			return;
		}
		if (generateBtn) generateBtn.disabled = true;
		if (generateSpinner) generateSpinner.classList.add('is-active');

		var body = JSON.stringify({ input: raw, tone: getTone() });
		var headers = {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce
		};

		fetch(apiUrl, {
			method: 'POST',
			headers: headers,
			body: body,
			credentials: 'same-origin'
		})
			.then(function (res) {
				return res.json().then(function (data) {
					return { ok: res.ok, status: res.status, data: data };
				});
			})
			.then(function (result) {
				if (result.ok && result.data && result.data.data && result.data.data.output) {
					setOutput(result.data.data.output);
					showMessage('', '');
				} else {
					var msg = (result.data && result.data.message) ? result.data.message : 'Request failed.';
					if (result.data && result.data.code) {
						msg = result.data.message || result.data.code;
					}
					showMessage(msg || 'Something went wrong.', 'error');
				}
			})
			.catch(function (err) {
				showMessage('Network or server error. Please try again.', 'error');
			})
			.finally(function () {
				if (generateBtn) generateBtn.disabled = false;
				if (generateSpinner) generateSpinner.classList.remove('is-active');
			});
	}

	if (inputEl) {
		inputEl.addEventListener('input', updateCount);
		inputEl.addEventListener('paste', function () {
			setTimeout(updateCount, 0);
		});
		updateCount();
	}

	if (generateBtn) {
		generateBtn.addEventListener('click', generate);
	}

	if (copyBtn) {
		copyBtn.addEventListener('click', copyToClipboard);
	}
})();
