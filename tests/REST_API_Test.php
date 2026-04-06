<?php
/**
 * Tests for {@see \Nilambar\Notira\API\REST_API}.
 *
 * @package Notira
 */

declare(strict_types=1);

/**
 * REST API integration tests (mirrors app/API/REST_API.php).
 */
class REST_API_Test extends WP_UnitTestCase {

	/**
	 * POST /notira/v1/generate and return the REST response.
	 *
	 * @param array<string, mixed> $params Body parameters.
	 */
	private function post_generate( array $params ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/notira/v1/generate' );
		foreach ( $params as $key => $value ) {
			$request->set_param( (string) $key, $value );
		}
		$response = rest_do_request( $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $response );

		return $response;
	}

	/**
	 * Assert validate_callback failure: top-level code is rest_invalid_param; Notira code lives under data.details.
	 *
	 * @param string $param               Request parameter name (e.g. input, mode).
	 * @param string $expected_notira_code Error code from the route validate_callback (e.g. notira_input_too_short).
	 */
	private function assert_rest_validate_callback_error( \WP_REST_Response $response, string $param, string $expected_notira_code ): void {
		$this->assertSame( 400, $response->get_status() );
		$payload = $response->get_data();
		$this->assertIsArray( $payload );
		$this->assertSame( 'rest_invalid_param', $payload['code'] );
		$this->assertArrayHasKey( 'data', $payload );
		$this->assertIsArray( $payload['data'] );
		$this->assertArrayHasKey( 'details', $payload['data'] );
		$this->assertIsArray( $payload['data']['details'] );
		$this->assertArrayHasKey( $param, $payload['data']['details'] );
		$detail = $payload['data']['details'][ $param ];
		$this->assertIsArray( $detail );
		$this->assertSame( $expected_notira_code, $detail['code'] );
	}

	/**
	 * Remove stored API keys for Connectors AI providers so credential checks stay deterministic.
	 */
	private function clear_connector_api_keys(): void {
		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return;
		}
		$connectors = wp_get_connectors();
		if ( ! is_array( $connectors ) ) {
			return;
		}
		foreach ( $connectors as $connector ) {
			if ( ! is_array( $connector ) || ! isset( $connector['authentication'] ) || ! is_array( $connector['authentication'] ) ) {
				continue;
			}
			$auth = $connector['authentication'];
			if ( ! isset( $auth['setting_name'] ) || ! is_string( $auth['setting_name'] ) || '' === $auth['setting_name'] ) {
				continue;
			}
			delete_option( $auth['setting_name'] );
		}
	}

	public function test_generate_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/notira/v1/generate', $routes );
	}

	public function test_generate_forbidden_for_guest(): void {
		wp_set_current_user( 0 );
		$response = $this->post_generate(
			[
				'input' => str_repeat( 'a', 25 ),
				'mode'  => 'email',
			]
		);
		$this->assertSame( 401, $response->get_status(), 'Anonymous users receive 401 from rest_authorization_required_code().' );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'rest_forbidden', $data['code'] );
	}

	public function test_generate_forbidden_for_subscriber(): void {
		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );
		$response = $this->post_generate(
			[
				'input' => str_repeat( 'a', 25 ),
				'mode'  => 'email',
			]
		);
		$this->assertSame( 403, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'rest_forbidden', $data['code'] );
	}

	public function test_generate_rejects_short_input(): void {
		$this->login_as_admin();
		$response = $this->post_generate(
			[
				'input' => str_repeat( 'a', 19 ),
				'mode'  => 'email',
			]
		);
		$this->assert_rest_validate_callback_error( $response, 'input', 'notira_input_too_short' );
	}

	public function test_generate_rejects_long_input(): void {
		$this->login_as_admin();
		$response = $this->post_generate(
			[
				'input' => str_repeat( 'a', 2001 ),
				'mode'  => 'email',
			]
		);
		$this->assert_rest_validate_callback_error( $response, 'input', 'notira_input_too_long' );
	}

	public function test_generate_rejects_invalid_mode(): void {
		$this->login_as_admin();
		$response = $this->post_generate(
			[
				'input' => str_repeat( 'a', 25 ),
				'mode'  => 'invalid-mode-slug',
			]
		);
		$this->assert_rest_validate_callback_error( $response, 'mode', 'notira_invalid_mode' );
	}

	public function test_generate_returns_503_when_ai_is_unsupported(): void {
		add_filter( 'wp_supports_ai', '__return_false' );
		$this->login_as_admin();
		try {
			$response = $this->post_generate(
				[
					'input' => str_repeat( 'a', 25 ),
					'mode'  => 'email',
				]
			);
		} finally {
			remove_filter( 'wp_supports_ai', '__return_false' );
		}
		$this->assertSame( 503, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'notira_ai_unsupported', $data['code'] );
	}

	public function test_generate_returns_503_when_no_api_key_configured(): void {
		if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
			$this->markTestSkipped( 'WordPress AI is disabled; cannot assert the no-credentials branch.' );
		}
		$this->clear_connector_api_keys();
		$this->login_as_admin();
		$response = $this->post_generate(
			[
				'input' => str_repeat( 'a', 25 ),
				'mode'  => 'email',
			]
		);
		$this->assertSame( 503, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'notira_no_api_key', $data['code'] );
	}

	private function login_as_admin(): void {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
	}
}
