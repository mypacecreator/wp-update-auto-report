<?php
/**
 * Anthropic API クライアントクラス
 *
 * @package WP_Update_Auto_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WUAR_Anthropic_Client
 *
 * Anthropic API を使用してテキスト生成を行う
 */
class WUAR_Anthropic_Client {

	/**
	 * オプションキー（APIキー保存）
	 */
	const OPTION_KEY_API_KEY = 'wuar_anthropic_api_key';

	/**
	 * オプションキー（モデルID保存）
	 */
	const OPTION_KEY_MODEL = 'wuar_anthropic_model';

	/**
	 * APIキーを保存
	 *
	 * @param string $api_key APIキー
	 * @return bool 保存成功
	 */
	public static function save_api_key( string $api_key ): bool {
		if ( empty( $api_key ) ) {
			return delete_option( self::OPTION_KEY_API_KEY );
		}

		return update_option( self::OPTION_KEY_API_KEY, sanitize_text_field( $api_key ) );
	}

	/**
	 * APIキーを取得
	 *
	 * @return string|false APIキー、存在しない場合はfalse
	 */
	public static function get_api_key() {
		return get_option( self::OPTION_KEY_API_KEY, false );
	}

	/**
	 * モデルIDを保存
	 *
	 * @param string $model_id モデルID
	 * @return bool 保存成功
	 */
	public static function save_model( string $model_id ): bool {
		if ( empty( $model_id ) ) {
			return delete_option( self::OPTION_KEY_MODEL );
		}

		// デフォルトモデル一覧から存在確認
		$available_models = WUAR_Anthropic_Models::get_available_models();
		if ( ! isset( $available_models[ $model_id ] ) ) {
			return false;
		}

		return update_option( self::OPTION_KEY_MODEL, sanitize_text_field( $model_id ) );
	}

	/**
	 * モデルIDを取得
	 *
	 * @return string モデルID
	 */
	public static function get_model(): string {
		$model = get_option( self::OPTION_KEY_MODEL, WUAR_Anthropic_Models::get_default_model_id() );

		if ( empty( $model ) ) {
			return WUAR_Anthropic_Models::get_default_model_id();
		}

		// 有効性チェック
		$available_models = WUAR_Anthropic_Models::get_available_models();
		if ( ! isset( $available_models[ $model ] ) ) {
			return WUAR_Anthropic_Models::get_default_model_id();
		}

		return $model;
	}

	/**
	 * Anthropic API設定が有効か確認
	 *
	 * @return bool 有効かどうか
	 */
	public static function is_configured(): bool {
		$api_key = self::get_api_key();
		return ! empty( $api_key );
	}

	/**
	 * APIキーとモデルの接続テストを実行
	 *
	 * @return array|WP_Error テスト結果（成功時は配列、失敗時はWP_Error）
	 */
	public static function test_connection() {
		$api_key = self::get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'no_api_key',
				__( 'API key is not configured', 'wp-update-auto-report' )
			);
		}

		// 現在のモデルで接続テスト
		$model = self::get_model();
		$result = WUAR_Anthropic_Models::call_api(
			$api_key,
			$model,
			'Hello',
			10
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'success' => true,
			'model'   => $model,
			'message' => __( 'Connection successful', 'wp-update-auto-report' ),
		];
	}

	/**
	 * プロンプトを実行して生成テキストを取得
	 *
	 * @param string $prompt プロンプト
	 * @param int $max_tokens 最大トークン数
	 * @return string|WP_Error 生成されたテキスト、またはエラー
	 */
	public static function generate( string $prompt, int $max_tokens = 2048 ) {
		if ( ! self::is_configured() ) {
			return new WP_Error(
				'not_configured',
				__( 'Anthropic API is not configured', 'wp-update-auto-report' )
			);
		}

		$api_key = self::get_api_key();
		$model   = self::get_model();

		$result = WUAR_Anthropic_Models::call_api(
			$api_key,
			$model,
			$prompt,
			$max_tokens
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// レスポンスからテキストを抽出
		$text = self::extract_text_from_response( $result );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return $text;
	}

	/**
	 * APIレスポンスからテキストを抽出
	 *
	 * @param array $response APIレスポンス
	 * @return string|WP_Error 抽出されたテキスト、またはエラー
	 */
	private static function extract_text_from_response( array $response ) {
		if ( empty( $response['content'] ) || ! is_array( $response['content'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'Invalid API response format', 'wp-update-auto-report' )
			);
		}

		$content = $response['content'];
		if ( empty( $content[0]['text'] ) ) {
			return new WP_Error(
				'no_text_content',
				__( 'No text content in API response', 'wp-update-auto-report' )
			);
		}

		return $content[0]['text'];
	}
}
