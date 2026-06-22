<?php
/**
 * Anthropic AI 設定ページ
 *
 * @package WP_Update_Auto_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WUAR_AI_Settings_Page
 *
 * 管理画面に Anthropic AI 設定ページを追加
 */
class WUAR_AI_Settings_Page {

	/**
	 * コンストラクタ
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_wuar_test_connection', [ $this, 'ajax_test_connection' ] );
	}

	/**
	 * 設定ページをメニューに追加
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'WP Update Auto Report - Anthropic AI Settings', 'wp-update-auto-report' ),
			__( 'WP Report AI Settings', 'wp-update-auto-report' ),
			'manage_options',
			'wuar-ai-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * 設定項目を登録
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// API キー
		register_setting(
			'wuar-ai-settings',
			WUAR_Anthropic_Client::OPTION_KEY_API_KEY,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_api_key' ],
				'show_in_rest'      => false,
			]
		);

		// モデル選択
		register_setting(
			'wuar-ai-settings',
			WUAR_Anthropic_Client::OPTION_KEY_MODEL,
			[
				'type'              => 'string',
				'default'           => WUAR_Anthropic_Models::get_default_model_id(),
				'sanitize_callback' => [ $this, 'sanitize_model' ],
				'show_in_rest'      => false,
			]
		);

		// セクション追加
		add_settings_section(
			'wuar-ai-api',
			__( 'Anthropic API Settings', 'wp-update-auto-report' ),
			[ $this, 'render_section_description' ],
			'wuar-ai-settings'
		);

		// APIキー フィールド
		add_settings_field(
			WUAR_Anthropic_Client::OPTION_KEY_API_KEY,
			__( 'API Key', 'wp-update-auto-report' ),
			[ $this, 'render_api_key_field' ],
			'wuar-ai-settings',
			'wuar-ai-api'
		);

		// モデル選択 フィールド
		add_settings_field(
			WUAR_Anthropic_Client::OPTION_KEY_MODEL,
			__( 'Model', 'wp-update-auto-report' ),
			[ $this, 'render_model_field' ],
			'wuar-ai-settings',
			'wuar-ai-api'
		);

		// ステータス表示 フィールド
		add_settings_field(
			'wuar-ai-status',
			__( 'Status', 'wp-update-auto-report' ),
			[ $this, 'render_status_field' ],
			'wuar-ai-settings',
			'wuar-ai-api'
		);
	}

	/**
	 * セクション説明をレンダリング
	 *
	 * @return void
	 */
	public function render_section_description(): void {
		echo wp_kses_post(
			'<p>' . __( 'Configure your Anthropic API key and select the model to use for generating AI reports.', 'wp-update-auto-report' ) . '</p>'
		);
	}

	/**
	 * APIキー フィールドをレンダリング
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		$api_key = WUAR_Anthropic_Client::get_api_key();
		$masked  = ! empty( $api_key ) ? '••••••••••••••••' : '';

		echo '<input type="password"
			name="' . esc_attr( WUAR_Anthropic_Client::OPTION_KEY_API_KEY ) . '"
			value="' . esc_attr( $masked ) . '"
			placeholder="sk-ant-..."
			class="regular-text" />';

		if ( ! empty( $api_key ) ) {
			echo '<p class="description">' . esc_html__( '(API key is already set. Enter a new one to change it.)', 'wp-update-auto-report' ) . '</p>';
		}
	}

	/**
	 * モデル選択 フィールドをレンダリング
	 *
	 * @return void
	 */
	public function render_model_field(): void {
		$current_model = WUAR_Anthropic_Client::get_model();
		$models        = WUAR_Anthropic_Models::get_available_models();

		echo '<select name="' . esc_attr( WUAR_Anthropic_Client::OPTION_KEY_MODEL ) . '">';

		foreach ( $models as $model_id => $model_info ) {
			$selected = selected( $current_model, $model_id, false );
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $model_id ),
				$selected,
				esc_html( $model_info['name'] )
			);
		}

		echo '</select>';

		if ( ! empty( $models[ $current_model ] ) ) {
			echo '<p class="description">' . esc_html( $models[ $current_model ]['description'] ) . '</p>';
		}
	}

	/**
	 * ステータス表示フィールドをレンダリング
	 *
	 * @return void
	 */
	public function render_status_field(): void {
		if ( ! WUAR_Anthropic_Client::is_configured() ) {
			echo '<span class="dashicons dashicons-warning" style="color: #dc3545;"></span> ';
			echo esc_html__( 'Not configured', 'wp-update-auto-report' );
			return;
		}

		echo '<span class="dashicons dashicons-yes-alt" style="color: #28a745;"></span> ';
		echo esc_html__( 'Configured', 'wp-update-auto-report' );

		echo '<br/><button type="button" id="wuar-test-connection" class="button button-secondary" style="margin-top: 10px;">' . esc_html__( 'Test Connection', 'wp-update-auto-report' ) . '</button>';
		echo '<span id="wuar-test-result" style="margin-left: 10px;"></span>';
	}

	/**
	 * APIキー を サニタイズ
	 *
	 * @param mixed $value 入力値
	 * @return string サニタイズされた値
	 */
	public function sanitize_api_key( $value ): string {
		if ( empty( $value ) || '••••••••••••••••' === $value ) {
			// 既存キーを保持、またはクリア
			return WUAR_Anthropic_Client::get_api_key() ?? '';
		}

		return sanitize_text_field( $value );
	}

	/**
	 * モデル を サニタイズ
	 *
	 * @param mixed $value 入力値
	 * @return string サニタイズされた値
	 */
	public function sanitize_model( $value ): string {
		if ( empty( $value ) ) {
			return WUAR_Anthropic_Models::get_default_model_id();
		}

		$value = sanitize_text_field( $value );

		// デフォルトモデルリストで有効性チェック
		$models = WUAR_Anthropic_Models::get_available_models();
		if ( isset( $models[ $value ] ) ) {
			return $value;
		}

		return WUAR_Anthropic_Models::get_default_model_id();
	}

	/**
	 * 設定ページをレンダリング
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wuar-ai-settings' );
				do_settings_sections( 'wuar-ai-settings' );
				submit_button();
				?>
			</form>
		</div>

		<script>
			jQuery(document).ready(function($) {
				$('#wuar-test-connection').click(function(e) {
					e.preventDefault();
					var $btn = $(this);
					var $result = $('#wuar-test-result');

					$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'wp-update-auto-report' ) ); ?>');
					$result.html('');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'wuar_test_connection',
							nonce: '<?php echo wp_create_nonce( 'wuar_test_connection' ); ?>'
						},
						success: function(response) {
							if (response.success) {
								$result.html('<span style="color: #28a745;">✓ <?php echo esc_js( __( 'Connection successful', 'wp-update-auto-report' ) ); ?></span>');
							} else {
								$result.html('<span style="color: #dc3545;">✗ ' + response.data.message + '</span>');
							}
						},
						error: function() {
							$result.html('<span style="color: #dc3545;">✗ <?php echo esc_js( __( 'Connection test failed', 'wp-update-auto-report' ) ); ?></span>');
						},
						complete: function() {
							$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Test Connection', 'wp-update-auto-report' ) ); ?>');
						}
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * 接続テスト AJAX ハンドラー
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'wp-update-auto-report' ) ] );
		}

		check_ajax_referer( 'wuar_test_connection', 'nonce' );

		$result = WUAR_Anthropic_Client::test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( $result );
	}
}
