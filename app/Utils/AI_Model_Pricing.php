<?php
/**
 * List-price estimates for AI models (USD per 1M tokens).
 *
 * Rates are taken from provider documentation (OpenAI platform pricing, Anthropic
 * Claude pricing, Google Gemini API pricing). Standard / default API tiers only;
 * batch, caching, and flex discounts are not applied. Update this file when
 * providers change list prices.
 *
 * @package Nilambar\Notira
 * @since 1.0.0
 */

namespace Nilambar\Notira\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class AI_Model_Pricing.
 *
 * @since 1.0.0
 */
class AI_Model_Pricing {

	/**
	 * OpenAI standard tier: input and output USD per 1M tokens.
	 *
	 * @since 1.0.0
	 * @see https://platform.openai.com/docs/pricing
	 *
	 * @var array<string, array{input: float, output: float}>
	 */
	private const OPENAI = [
		'gpt-4o'             => [
			'input'  => 2.5,
			'output' => 10.0,
		],
		'gpt-4o-2024-05-13'  => [
			'input'  => 5.0,
			'output' => 15.0,
		],
		'gpt-4o-mini'        => [
			'input'  => 0.15,
			'output' => 0.6,
		],
		'gpt-3.5-turbo'      => [
			'input'  => 0.5,
			'output' => 1.5,
		],
		'gpt-3.5-turbo-0125' => [
			'input'  => 0.5,
			'output' => 1.5,
		],
		'gpt-3.5-turbo-1106' => [
			'input'  => 1.0,
			'output' => 2.0,
		],
		'gpt-3.5-turbo-0613' => [
			'input'  => 1.5,
			'output' => 2.0,
		],
	];

	/**
	 * Anthropic Claude: input and output USD per 1M tokens.
	 *
	 * @since 1.0.0
	 * @see https://docs.anthropic.com/en/docs/about-claude/pricing
	 *
	 * @var array<string, array{input: float, output: float}>
	 */
	private const ANTHROPIC = [
		'claude-3-5-sonnet-20241022' => [
			'input'  => 3.0,
			'output' => 15.0,
		],
		'claude-3-5-sonnet'          => [
			'input'  => 3.0,
			'output' => 15.0,
		],
		'claude-sonnet-4-20250514'   => [
			'input'  => 3.0,
			'output' => 15.0,
		],
		'claude-sonnet-4-5'          => [
			'input'  => 3.0,
			'output' => 15.0,
		],
	];

	/**
	 * Google Gemini Developer API: paid tier, text; USD per 1M tokens.
	 *
	 * @since 1.0.0
	 * @see https://ai.google.dev/gemini-api/docs/pricing
	 *
	 * @var array<string, array{input: float, output: float}>
	 */
	private const GOOGLE = [
		'gemini-2.0-flash' => [
			'input'  => 0.1,
			'output' => 0.4,
		],
		// Legacy model id still in Notira preferences; rate aligned to current Gemini 2.5 Pro ≤200k text tier.
		'gemini-1.5-pro'   => [
			'input'  => 1.25,
			'output' => 10.0,
		],
	];

	/**
	 * Estimate USD cost from response metadata, or null when not computable.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $meta Metadata including model.id, optional provider.id, token_usage.
	 * @return float|null Rounded cost in USD, or null if unknown model or missing usage.
	 */
	public static function estimate_from_meta( array $meta ): ?float {
		$model_id = '';
		if ( isset( $meta['model'] ) && is_array( $meta['model'] ) && isset( $meta['model']['id'] ) && is_string( $meta['model']['id'] ) ) {
			$model_id = $meta['model']['id'];
		}
		if ( '' === $model_id ) {
			return null;
		}

		$provider_id = '';
		if ( isset( $meta['provider'] ) && is_array( $meta['provider'] ) && isset( $meta['provider']['id'] ) && is_string( $meta['provider']['id'] ) ) {
			$provider_id = $meta['provider']['id'];
		}

		$token_usage = isset( $meta['token_usage'] ) && is_array( $meta['token_usage'] ) ? $meta['token_usage'] : [];
		if ( ! isset( $token_usage['promptTokens'], $token_usage['completionTokens'] ) ) {
			return null;
		}
		$prompt_tokens     = $token_usage['promptTokens'];
		$completion_tokens = $token_usage['completionTokens'];
		if ( ! is_int( $prompt_tokens ) && ! is_float( $prompt_tokens ) ) {
			return null;
		}
		if ( ! is_int( $completion_tokens ) && ! is_float( $completion_tokens ) ) {
			return null;
		}
		$prompt_tokens     = (int) $prompt_tokens;
		$completion_tokens = (int) $completion_tokens;
		if ( $prompt_tokens < 0 || $completion_tokens < 0 ) {
			return null;
		}

		$vendor = self::resolve_vendor( $provider_id, $model_id );
		if ( null === $vendor ) {
			return null;
		}

		$rates = self::lookup_rates( $vendor, $model_id );
		if ( null === $rates ) {
			return null;
		}

		$cost = ( $prompt_tokens / 1000000.0 ) * $rates['input']
			+ ( $completion_tokens / 1000000.0 ) * $rates['output'];

		return round( $cost, 6 );
	}

	/**
	 * Resolve vendor slug from provider metadata or model id.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id Provider id from AI client.
	 * @param string $model_id    Model id from AI client.
	 * @return string|null openai, anthropic, google, or null.
	 */
	private static function resolve_vendor( string $provider_id, string $model_id ): ?string {
		$pid = strtolower( trim( $provider_id ) );
		if ( in_array( $pid, [ 'openai', 'anthropic', 'google' ], true ) ) {
			return $pid;
		}

		$mid = strtolower( trim( $model_id ) );
		if ( 0 === strpos( $mid, 'claude-' ) ) {
			return 'anthropic';
		}
		if ( 0 === strpos( $mid, 'gemini-' ) ) {
			return 'google';
		}
		if ( 0 === strpos( $mid, 'gpt-' ) || 0 === strpos( $mid, 'o1' ) || 0 === strpos( $mid, 'o3' ) || 0 === strpos( $mid, 'o4' ) ) {
			return 'openai';
		}

		return null;
	}

	/**
	 * Normalize model id for table lookup (lowercase, strip dated snapshots).
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Raw model id.
	 * @return string
	 */
	private static function normalize_model_id( string $model_id ): string {
		$id = strtolower( trim( $model_id ) );
		$id = preg_replace( '/@.+$/', '', $id );
		$id = preg_replace( '/-\d{4}-\d{2}-\d{2}$/', '', $id );
		$id = preg_replace( '/-\d{8}$/', '', $id );
		return is_string( $id ) ? $id : strtolower( trim( $model_id ) );
	}

	/**
	 * Find input/output rates for a vendor and model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $vendor   openai|anthropic|google.
	 * @param string $model_id Raw model id from API.
	 * @return array{input: float, output: float}|null
	 */
	private static function lookup_rates( string $vendor, string $model_id ): ?array {
		$table = [];
		if ( 'openai' === $vendor ) {
			$table = self::OPENAI;
		} elseif ( 'anthropic' === $vendor ) {
			$table = self::ANTHROPIC;
		} elseif ( 'google' === $vendor ) {
			$table = self::GOOGLE;
		} else {
			return null;
		}

		$normalized = self::normalize_model_id( $model_id );
		$lower      = strtolower( trim( $model_id ) );

		if ( isset( $table[ $normalized ] ) ) {
			return $table[ $normalized ];
		}
		if ( isset( $table[ $lower ] ) ) {
			return $table[ $lower ];
		}

		$best_len  = -1;
		$best_rate = null;
		foreach ( array_keys( $table ) as $prefix_key ) {
			if ( $normalized === $prefix_key ) {
				return $table[ $prefix_key ];
			}
			if ( strlen( $prefix_key ) > $best_len && 0 === strpos( $normalized, $prefix_key . '-' ) ) {
				$best_len  = strlen( $prefix_key );
				$best_rate = $table[ $prefix_key ];
			}
		}

		return $best_rate;
	}
}
