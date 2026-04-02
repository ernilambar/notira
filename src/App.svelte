<script>
	import { onDestroy, onMount } from 'svelte';
	import { fade, fly } from 'svelte/transition';
	import { buildGenerationMetaLines, copyToClipboard, debounce } from './lib/utils.js';

	const admin = typeof window !== 'undefined' ? window.notiraAdmin : null;
	const cfg = admin && typeof admin === 'object' ? admin : {};

	const {
		apiUrl = '',
		nonce = '',
		aiUiEnabled = false,
		defaultMode = 'email',
		modes = [],
		defaultTone = '',
		tones = [],
	} = cfg;

	/**
	 * Input length limits come only from #notira-root data-* (templates/admin-page.php), not
	 * window.notiraAdmin, so they are always present when the mount node exists.
	 */
	let minLength = $state( 0 );
	let maxLength = $state( 0 );

	/** @type {Record<string, string>} */
	const i18n = cfg.i18n && typeof cfg.i18n === 'object' ? cfg.i18n : {};

	const modeList = Array.isArray( modes ) ? modes : [];
	const modeValues = modeList.map( ( m ) => m?.value ).filter( Boolean );
	let initialMode = defaultMode;
	if ( initialMode && ! modeValues.includes( initialMode ) ) {
		initialMode = modeList[ 0 ]?.value ?? 'email';
	} else if ( ! initialMode && modeList.length ) {
		initialMode = modeList[ 0 ].value;
	} else if ( ! initialMode ) {
		initialMode = 'email';
	}

	const toneList = Array.isArray( tones ) ? tones : [];
	const toneValues = toneList.map( ( t ) => t?.value ).filter( Boolean );
	let initialTone = defaultTone;
	if ( initialTone && ! toneValues.includes( initialTone ) ) {
		initialTone = toneList[ 0 ]?.value ?? '';
	} else if ( ! initialTone && toneList.length ) {
		initialTone = toneList[ 0 ].value;
	}

	let inputValue = $state( '' );
	let selectedMode = $state( initialMode );
	let selectedTone = $state( initialTone );
	let outputHtml = $state( '' );
	let generationMeta = $state( null );
	let charCountError = $state( false );
	/** @type {'' | 'empty' | 'short' | 'long'} */
	let validationIssue = $state( '' );
	let noticeMessage = $state( '' );
	let noticeType = $state( 'success' );
	let generating = $state( false );
	let copyShowingCopied = $state( false );

	/** @type {HTMLTextAreaElement | undefined} */
	let textareaEl = $state();
	/** @type {ReturnType<typeof setTimeout> | null} */
	let inputErrorTimeoutId = null;
	/** @type {ReturnType<typeof setTimeout> | null} */
	let copyTimeoutId = null;

	const charCount = $derived( inputValue.length );
	const atLimit = $derived( maxLength > 0 && charCount >= maxLength );
	const exceedsMax = $derived( maxLength > 0 && charCount > maxLength );
	const metaLines = $derived( buildGenerationMetaLines( generationMeta, i18n ) );
	const hasOutput = $derived( outputHtml.trim().length > 0 );
	const noticeVisible = $derived( noticeMessage.trim().length > 0 );

	const validationMessage = $derived(
		validationIssue === 'empty'
			? i18n.pleaseEnterText || ''
			: validationIssue === 'short'
			? i18n.inputTooShort || ''
			: validationIssue === 'long'
			? i18n.textTooLong || ''
			: ''
	);

	const inputHasValidationError = $derived(
		charCountError ||
			validationIssue === 'empty' ||
			validationIssue === 'short' ||
			validationIssue === 'long'
	);

	$effect( () => {
		const raw = inputValue.trim();
		if ( raw ) {
			charCountError = false;
		}
		if ( ! validationIssue ) {
			return;
		}
		if ( validationIssue === 'empty' && raw ) {
			validationIssue = '';
			return;
		}
		if (
			validationIssue === 'short' &&
			raw.length >= minLength &&
			( maxLength === 0 || raw.length <= maxLength )
		) {
			validationIssue = '';
			return;
		}
		if ( validationIssue === 'long' && maxLength > 0 && raw.length <= maxLength ) {
			validationIssue = '';
		}
	} );

	function setNotice( message, type ) {
		if ( ! message ) {
			noticeMessage = '';
			noticeType = 'success';
			return;
		}
		noticeMessage = message;
		noticeType = type === 'error' ? 'error' : 'success';
	}

	function getOutputPlainText() {
		const tmp = document.createElement( 'div' );
		tmp.innerHTML = outputHtml || '';
		return tmp.innerText || tmp.textContent || '';
	}

	/**
	 * Read limits from #notira-root data-* attributes (explicit names; avoid dataset camelCase quirks).
	 * The textarea has no HTML maxlength so long pastes are not silently clipped; length is enforced in generate().
	 */
	function readLimitAttributes() {
		const root = document.getElementById( 'notira-root' );
		if ( ! root ) {
			return { minCap: 0, maxCap: 0 };
		}
		const maxParsed = Number.parseInt( root.getAttribute( 'data-max-length' ) ?? '', 10 );
		const minParsed = Number.parseInt( root.getAttribute( 'data-min-length' ) ?? '', 10 );
		const maxCap = Number.isNaN( maxParsed ) ? 0 : maxParsed;
		const minCap = Number.isNaN( minParsed ) ? 0 : minParsed;
		return { minCap, maxCap };
	}

	function applyLimitsToUi( minCap, maxCap ) {
		minLength = minCap;
		maxLength = maxCap;
	}

	function generate() {
		const { minCap, maxCap } = readLimitAttributes();
		applyLimitsToUi( minCap, maxCap );

		const raw = inputValue.trim();
		const len = raw.length;

		if ( ! raw ) {
			if ( inputErrorTimeoutId ) {
				clearTimeout( inputErrorTimeoutId );
			}
			validationIssue = 'empty';
			textareaEl?.focus();
			charCountError = true;
			inputErrorTimeoutId = setTimeout( () => {
				charCountError = false;
				inputErrorTimeoutId = null;
			}, 3000 );
			return;
		}
		if ( minCap > 0 && len < minCap ) {
			validationIssue = 'short';
			textareaEl?.focus();
			return;
		}
		if ( maxCap > 0 && len > maxCap ) {
			validationIssue = 'long';
			textareaEl?.focus();
			return;
		}
		validationIssue = '';
		setNotice();
		generating = true;
		generationMeta = null;

		const body = JSON.stringify( {
			input: raw,
			tone: selectedTone,
			mode: selectedMode,
		} );
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
					outputHtml = result.data.data.output;
					generationMeta = result.data.data.meta ?? null;
					setNotice( i18n.generatedSuccess || '', 'success' );
				} else {
					generationMeta = null;
					const msg =
						result.data?.message ||
						result.data?.code ||
						i18n.requestFailed ||
						i18n.somethingWentWrong;
					setNotice( msg || '', 'error' );
				}
			} )
			.catch( () => {
				generationMeta = null;
				setNotice( i18n.networkError || '', 'error' );
			} )
			.finally( () => {
				generating = false;
			} );
	}

	function handleCopy() {
		if ( ! hasOutput ) {
			setNotice( i18n.nothingToCopy || '', 'error' );
			return;
		}
		const html = outputHtml || '';
		const text = getOutputPlainText();

		copyToClipboard( { text, html } )
			.then( () => {
				if ( copyTimeoutId ) {
					clearTimeout( copyTimeoutId );
				}
				copyShowingCopied = true;
				copyTimeoutId = setTimeout( () => {
					copyShowingCopied = false;
					copyTimeoutId = null;
				}, 3000 );
			} )
			.catch( ( err ) => {
				const msg =
					err?.code === 'NOTHING_TO_COPY' ? i18n.nothingToCopy : i18n.copyFailedManual;
				setNotice( msg || '', 'error' );
			} );
	}

	const debouncedGenerate = debounce( generate, 300 );

	onMount( () => {
		const { minCap, maxCap } = readLimitAttributes();
		applyLimitsToUi( minCap, maxCap );
	} );

	onDestroy( () => {
		if ( inputErrorTimeoutId ) {
			clearTimeout( inputErrorTimeoutId );
		}
		if ( copyTimeoutId ) {
			clearTimeout( copyTimeoutId );
		}
	} );
</script>

<div class="notira-columns">
	<div class="notira-column-left">
		<div class="notira-panel">
			<div class="notira-input-section">
				<label for="notira-input-svelte">{i18n.inputLabel}</label>

				<div class="notira-mode-section">
					<span class="notira-mode-section-label" id="notira-mode-legend"
						>{i18n.modeLabel}</span
					>
					<div
						class="notira-mode-radios"
						role="group"
						aria-labelledby="notira-mode-legend"
					>
						{#each modeList as item (item.value)}
							<label
								class="notira-mode-option"
								title={item.help ? item.help : undefined}
							>
								<input
									type="radio"
									name="notira-mode"
									value={item.value}
									bind:group={selectedMode}
									disabled={! aiUiEnabled}
								/>
								<span class="notira-mode-option-label">{item.label}</span>
							</label>
						{/each}
					</div>
				</div>

				<textarea
					id="notira-input-svelte"
					bind:this={textareaEl}
					class="notira-textarea"
					class:notira-textarea--error={inputHasValidationError || exceedsMax}
					rows="10"
					bind:value={inputValue}
					placeholder={i18n.inputPlaceholder}
					disabled={! aiUiEnabled}
					aria-invalid={inputHasValidationError || exceedsMax}
					aria-describedby={validationMessage
						? 'notira-char-count-line notira-input-validation'
						: 'notira-char-count-line'}
				></textarea>

				<p
					id="notira-char-count-line"
					class="description notira-char-count"
					class:notira-char-count-error={charCountError ||
						validationIssue === 'empty' ||
						validationIssue === 'short' ||
						validationIssue === 'long' ||
						exceedsMax}
					class:notira-at-limit={atLimit}
				>
					{charCount}
					{i18n.charCountMiddle}
					{maxLength}
					{i18n.charactersWord}
					<span class="notira-char-limits">({i18n.minCharsHint})</span>
					{#if validationMessage}
						<span id="notira-input-validation" class="screen-reader-text" role="alert"
							>{validationMessage}</span
						>
					{/if}
				</p>
			</div>

			<div class="notira-tone-section">
				<label for="notira-tone-svelte">{i18n.toneLabel}</label>
				<select id="notira-tone-svelte" bind:value={selectedTone} disabled={! aiUiEnabled}>
					{#each toneList as item (item.value)}
						<option value={item.value}>{item.label}</option>
					{/each}
				</select>
			</div>

			<div class="notira-actions">
				<button
					type="button"
					class="button button-primary"
					disabled={! aiUiEnabled}
					aria-busy={generating}
					onclick={debouncedGenerate}
				>
					{i18n.generateLabel}
				</button>
				<span
					class="notira-spinner-host"
					class:is-active={generating}
					role="status"
					aria-live="polite"
					aria-label={generating ? i18n.generatingLabel : undefined}
				>
					<svg
						class="notira-spinner-svg"
						xmlns="http://www.w3.org/2000/svg"
						width="20"
						height="20"
						viewBox="0 0 24 24"
						fill="none"
						aria-hidden="true"
					>
						<circle
							class="notira-spinner-track"
							cx="12"
							cy="12"
							r="10"
							stroke="currentColor"
							stroke-width="3"
							fill="none"
						/>
						<path
							d="M12 2a10 10 0 0 1 10 10"
							stroke="currentColor"
							stroke-width="3"
							stroke-linecap="round"
							fill="none"
						/>
					</svg>
				</span>
			</div>
			<div
				class="notira-generation-meta"
				class:is-empty={metaLines.length === 0}
				role="status"
				aria-live="polite"
			>
				{#each metaLines as line, i (i)}
					<div class="notira-generation-meta-line">{line}</div>
				{/each}
			</div>
		</div>

		<div class="notira-notice-host">
			{#if noticeVisible}
				<div
					class="notira-notice"
					class:notira-notice--success={noticeType === 'success'}
					class:notira-notice--error={noticeType === 'error'}
					role={noticeType === 'error' ? 'alert' : 'status'}
					aria-live={noticeType === 'error' ? 'assertive' : 'polite'}
					aria-hidden="false"
					in:fly={{ duration: 220, y: -6, opacity: 0 }}
					out:fade={{ duration: 160 }}
				>
					{noticeMessage}
				</div>
			{/if}
		</div>
	</div>

	<div class="notira-column-right">
		<div class="notira-output-section">
			<div class="notira-output-header">
				<span id="notira-output-title" class="notira-output-title">{i18n.outputLabel}</span>
				<button
					type="button"
					class="button notira-copy-btn"
					disabled={! aiUiEnabled}
					onclick={handleCopy}
				>
					<span class="notira-copy-label-wrap">
						<span class="notira-copy-label"
							>{copyShowingCopied ? i18n.copiedLabel : i18n.copyLabel}</span
						>
					</span>
				</button>
			</div>
			<div
				id="notira-output-region"
				class="notira-output"
				class:notira-output--filled={hasOutput}
				role="region"
				aria-labelledby="notira-output-title"
				aria-live="polite"
			>
				{#if hasOutput}
					<div class="notira-output-html">{@html outputHtml}</div>
				{:else}
					<span class="notira-output-placeholder" aria-hidden="true"
						>{i18n.outputPlaceholder}</span
					>
				{/if}
			</div>
		</div>
	</div>
</div>
