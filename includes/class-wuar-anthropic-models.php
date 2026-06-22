<?php
/**
 * Anthropic モデル管理クラス
 *
 * @package WP_Update_Auto_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WUAR_Anthropic_Models
 *
 * Anthropic API のモデル管理、キャッシング、廃止検出を担当
 */
class WUAR_Anthropic_Models {

	/**
	 * キャッシュキー
	 */
	const CACHE_KEY = 'wuar_anthropic_models';

	/**
	 * キャッシュ有効期限（秒）：24時間
	 */
	const CACHE_DURATION = 86400;

	/**
	 * デフォルトモデル定義
	 */
	const DEFAULT_MODELS = [
		'claude-opus-4-8' => [
			'id'          => 'claude-opus-4-8',
			'name'        => 'Claude Opus 4.8',
			'description' => 'Most capable model, best for complex tasks',
		],
		'claude-sonnet-4' => [
			'id'          => 'claude-sonnet-4',
			'name'        => 'Claude Sonnet 4',
			'description' => 'Balanced speed and capability',
		],
		'claude-haiku' => [
			'id'          => 'claude-haiku',
			'name'        => 'Claude Haiku',
			'description' => 'Fastest and cheapest model',
		],
	];

	/**
	 * 利用可能なモデルを取得（キャッシュから）
	 *
	 * @return array モデル配列
	 */
	public static function get_available_models(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// キャッシュなし → デフォルトを返す
		// 実行時検証は接続テスト時に行う
		return self::DEFAULT_MODELS;
	}

	/**
	 * モデルが有効か検証（API呼び出し）
	 *
	 * @param string $api_key APIキー
	 * @param string $model_id モデルID
	 * @return bool 有効かどうか
	 */
	public static function validate_model( string $api_key, string $model_id ): bool {
		if ( empty( $api_key ) || empty( $model_id ) ) {
			return false;
		}

		// テスト用プロンプト
		$test_prompt = 'Test';

		$response = self::call_api( $api_key, $model_id, $test_prompt, 10 );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		return true;
	}

	/**
	 * すべてのデフォルトモデルの有効性を検証
	 *
	 * @param string $api_key APIキー
	 * @return array 有効なモデル配列
	 */
	public static function validate_all_models( string $api_key ): array {
		if ( empty( $api_key ) ) {
			return [];
		}

		$valid_models = [];

		foreach ( self::DEFAULT_MODELS as $model_id => $model_info ) {
			if ( self::validate_model( $api_key, $model_id ) ) {
				$valid_models[ $model_id ] = $model_info;
			}
		}

		// キャッシュ保存
		if ( ! empty( $valid_models ) ) {
			set_transient( self::CACHE_KEY, $valid_models, self::CACHE_DURATION );
		}

		return $valid_models;
	}

	/**
	 * Anthropic API を呼び出し
	 *
	 * @param string $api_key APIキー
	 * @param string $model_id モデルID
	 * @param string $prompt プロンプト
	 * @param int $max_tokens 最大トークン数
	 * @return array|WP_Error レスポンス配列またはエラー
	 */
	public static function call_api( string $api_key, string $model_id, string $prompt, int $max_tokens = 1024 ) {
		if ( empty( $api_key ) || empty( $model_id ) || empty( $prompt ) ) {
			return new WP_Error( 'invalid_params', __( 'Invalid parameters', 'wp-update-auto-report' ) );
		}

		$url = 'https://api.anthropic.com/v1/messages';

		$args = [
			'headers'   => [
				'x-api-key'       => $api_key,
				'anthropic-version' => '2023-06-01',
				'content-type'    => 'application/json',
			],
			'body'      => wp_json_encode(
				[
					'model'       => $model_id,
					'max_tokens'  => $max_tokens,
					'messages'    => [
						[
							'role'    => 'user',
							'content' => $prompt,
						],
					],
				]
			),
			'timeout'   => 30,
			'sslverify' => true,
		];

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			$error_data = json_decode( $body, true );
			$error_msg  = $error_data['error']['message'] ?? 'Unknown error';

			return new WP_Error( 'api_error', $error_msg, [ 'status' => $status_code ] );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid API response', 'wp-update-auto-report' ) );
		}

		return $data;
	}

	/**
	 * デフォルトモデルIDを取得
	 *
	 * @return string デフォルトモデルID
	 */
	public static function get_default_model_id(): string {
		return 'claude-opus-4-8';
	}

	/**
	 * キャッシュをクリア
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}
