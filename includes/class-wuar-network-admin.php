<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WUAR_Network_Admin {

	private WUAR_Network_Version_Tracker $tracker;

	public function __construct() {
		$this->tracker = new WUAR_Network_Version_Tracker();

		add_action( 'network_admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'network_admin_edit_wuar_save_ai_model', [ $this, 'handle_save_ai_model' ] );
		add_action( 'wp_ajax_wuar_network_save_snapshot', [ $this, 'ajax_save_snapshot' ] );
		add_action( 'wp_ajax_wuar_network_generate_report', [ $this, 'ajax_generate_report' ] );
		add_action( 'wp_ajax_wuar_network_generate_ai_report', [ $this, 'ajax_generate_ai_report' ] );
		add_action( 'wp_ajax_wuar_network_reset_snapshot', [ $this, 'ajax_reset_snapshot' ] );
	}

	public function register_menu(): void {
		add_submenu_page(
			'settings.php',
			__( 'WP レポート生成（ネットワーク）', 'wp-update-auto-report' ),
			__( 'WP レポート生成', 'wp-update-auto-report' ),
			'manage_network',
			'wuar-network-report',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_wuar-network-report' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wuar-admin',
			WUAR_PLUGIN_URL . 'assets/css/wuar-admin.css',
			[],
			WUAR_VERSION
		);

		wp_enqueue_script(
			'wuar-network-admin',
			WUAR_PLUGIN_URL . 'assets/js/wuar-network-admin.js',
			[],
			WUAR_VERSION,
			true
		);

		wp_localize_script(
			'wuar-network-admin',
			'wuarNetworkData',
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'wuar_network_nonce' ),
				'today'         => wp_date( 'Ymd' ),
				'hasSnapshot'   => $this->tracker->get_snapshot() ? '1' : '0',
				'snapshotLabel' => $this->get_snapshot_label(),
			]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( '権限がありません。', 'wp-update-auto-report' ) );
		}

		$snapshot     = $this->tracker->get_snapshot();
		$has_snapshot = (bool) $snapshot;
		$diff_items   = $has_snapshot ? $this->tracker->get_diff_items() : [];
		?>
		<div class="wrap wuar-wrap">
			<h1><?php esc_html_e( 'WP レポート生成（ネットワーク全体）', 'wp-update-auto-report' ); ?></h1>
			<p><?php esc_html_e( 'ネットワークにインストールされている全プラグイン・全テーマ（有効・無効問わず）を対象に、コアも含めたアップデート差分をまとめてレポートします。', 'wp-update-auto-report' ); ?></p>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( '設定を保存しました。', 'wp-update-auto-report' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wuar-step">
				<h2><?php esc_html_e( 'STEP 1 — アップデート前にスナップショットを取得', 'wp-update-auto-report' ); ?></h2>
				<p><?php esc_html_e( 'アップデート作業を始める前に、現在のバージョン情報を記録してください。', 'wp-update-auto-report' ); ?></p>

				<p class="wuar-snapshot-status">
					<?php if ( $has_snapshot ) : ?>
						<span class="dashicons dashicons-yes-alt" style="color:#46b450;vertical-align:middle;"></span>
						<?php echo esc_html( $this->get_snapshot_label() ); ?>
					<?php else : ?>
						<span class="dashicons dashicons-warning" style="color:#ffb900;vertical-align:middle;"></span>
						<?php esc_html_e( 'スナップショット未取得', 'wp-update-auto-report' ); ?>
					<?php endif; ?>
				</p>

				<button id="wuar-snapshot-btn" class="button">
					<?php esc_html_e( '現在の状態をスナップショット', 'wp-update-auto-report' ); ?>
				</button>
				<span id="wuar-snapshot-msg" class="wuar-inline-msg" hidden></span>
			</div>

			<hr>

			<div class="wuar-step">
				<h2><?php esc_html_e( 'STEP 2 — アップデート後にレポートを生成', 'wp-update-auto-report' ); ?></h2>
				<p><?php esc_html_e( 'レポートは何度でも生成できます。確認後、STEP 3 でスナップショットをリセットしてください。', 'wp-update-auto-report' ); ?></p>

				<?php if ( ! $has_snapshot ) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'アップデート前にスナップショットを取得してください（STEP 1）。', 'wp-update-auto-report' ); ?></p>
					</div>
				<?php else : ?>
					<h3><?php esc_html_e( '差分プレビュー', 'wp-update-auto-report' ); ?></h3>
					<pre id="wuar-diff-preview" class="wuar-diff-preview"><?php
					if ( empty( $diff_items ) ) {
						esc_html_e( '変更はありません（スナップショット取得後のアップデートが検出されていません）。', 'wp-update-auto-report' );
					} else {
						foreach ( $diff_items as $item ) {
							printf(
								"【%s】%s: %s -> %s\n",
								esc_html( $item['type'] ),
								esc_html( $item['name'] ),
								esc_html( $item['before'] ),
								esc_html( $item['after'] )
							);
						}
					}
					?></pre>
				<?php endif; ?>

				<p>
					<button
						id="wuar-generate-btn"
						class="button button-primary"
						<?php echo $has_snapshot ? '' : 'disabled'; ?>
					>
						<?php esc_html_e( 'レポートを生成する', 'wp-update-auto-report' ); ?>
					</button>

					<?php if ( function_exists( 'wp_ai_client_prompt' ) ) : ?>
						<button
							id="wuar-ai-generate-btn"
							class="button button-secondary"
							<?php echo $has_snapshot ? '' : 'disabled'; ?>
						>
							<?php esc_html_e( 'AI詳細レポートを生成', 'wp-update-auto-report' ); ?>
						</button>
					<?php endif; ?>

					<span id="wuar-loading" class="wuar-loading" hidden>
						<?php esc_html_e( 'レポートを作成中...', 'wp-update-auto-report' ); ?>
					</span>
				</p>

				<div id="wuar-result-area" class="wuar-result-area" hidden>
					<textarea
						id="wuar-result"
						class="wuar-result"
						rows="22"
						readonly
					></textarea>

					<p class="wuar-actions">
						<button id="wuar-copy-btn" class="button">
							<?php esc_html_e( 'リッチテキストでコピー', 'wp-update-auto-report' ); ?>
						</button>
						<button id="wuar-download-btn" class="button">
							<?php esc_html_e( 'Markdown でダウンロード', 'wp-update-auto-report' ); ?>
						</button>
					</p>
				</div>
			</div>

			<hr>

			<div class="wuar-step">
				<h2><?php esc_html_e( 'STEP 3 — レポート確定（スナップショットをリセット）', 'wp-update-auto-report' ); ?></h2>
				<p><?php esc_html_e( 'レポートを確認し、作業が完了したらスナップショットをリセットしてください。次回のアップデート差分検出の準備が整います。', 'wp-update-auto-report' ); ?></p>

				<?php if ( ! $has_snapshot ) : ?>
					<div class="notice notice-info inline">
						<p><?php esc_html_e( 'スナップショットが存在しないため、リセットの必要はありません。', 'wp-update-auto-report' ); ?></p>
					</div>
				<?php else : ?>
					<button
						id="wuar-reset-snapshot-btn"
						class="button button-secondary"
					>
						<?php esc_html_e( 'スナップショットをリセット', 'wp-update-auto-report' ); ?>
					</button>
					<span id="wuar-reset-msg" class="wuar-inline-msg" hidden></span>
				<?php endif; ?>
			</div>

			<?php if ( function_exists( 'wp_ai_client_prompt' ) ) : ?>
			<hr>

			<div class="wuar-step">
				<h2><?php esc_html_e( '設定 — AI モデル選択', 'wp-update-auto-report' ); ?></h2>
				<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=wuar_save_ai_model' ) ); ?>">
					<?php wp_nonce_field( 'wuar_network_ai_model' ); ?>
					<?php $current_model = $this->get_current_ai_model(); ?>
					<select name="wuar_ai_model" id="wuar_ai_model">
						<?php foreach ( WUAR_AI_MODELS as $model_id => $label ) : ?>
							<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $current_model, $model_id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( '設定を保存', 'wp-update-auto-report' ) ); ?>
				</form>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_save_ai_model(): void {
		check_admin_referer( 'wuar_network_ai_model' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( '権限がありません。', 'wp-update-auto-report' ) );
		}

		$model_id = isset( $_POST['wuar_ai_model'] ) ? sanitize_text_field( wp_unslash( $_POST['wuar_ai_model'] ) ) : '';
		if ( ! array_key_exists( $model_id, WUAR_AI_MODELS ) ) {
			$model_id = WUAR_AI_MODEL_DEFAULT;
		}
		update_site_option( 'wuar_ai_model', $model_id );

		wp_safe_redirect( add_query_arg( 'updated', 'true', network_admin_url( 'settings.php?page=wuar-network-report' ) ) );
		exit;
	}

	private function get_current_ai_model(): string {
		$current = get_site_option( 'wuar_ai_model', WUAR_AI_MODEL_DEFAULT );
		if ( ! is_string( $current ) || ! array_key_exists( $current, WUAR_AI_MODELS ) ) {
			$current = WUAR_AI_MODEL_DEFAULT;
		}
		return $current;
	}

	public function ajax_save_snapshot(): void {
		check_ajax_referer( 'wuar_network_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( [ 'message' => __( '権限がありません。', 'wp-update-auto-report' ) ] );
		}

		$this->tracker->save_snapshot();
		$snapshot = $this->tracker->get_snapshot();

		if ( ! $snapshot ) {
			wp_send_json_error( [ 'message' => __( 'スナップショットの保存に失敗しました。', 'wp-update-auto-report' ) ] );
		}

		wp_send_json_success( [
			'recorded_at' => $snapshot['recorded_at'],
			'label'       => $this->get_snapshot_label(),
		] );
	}

	public function ajax_generate_report(): void {
		check_ajax_referer( 'wuar_network_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( [ 'message' => __( '権限がありません。', 'wp-update-auto-report' ) ] );
		}

		if ( ! $this->tracker->get_snapshot() ) {
			wp_send_json_error( [ 'message' => __( 'スナップショットが存在しません。STEP 1 でスナップショットを取得してください。', 'wp-update-auto-report' ) ] );
		}

		$report = $this->tracker->generate_fixed_report();

		wp_send_json_success( [ 'report' => $report ] );
	}

	public function ajax_generate_ai_report(): void {
		check_ajax_referer( 'wuar_network_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( [ 'message' => __( '権限がありません。', 'wp-update-auto-report' ) ] );
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			wp_send_json_error( [ 'message' => __( 'WordPress 7.0 以上が必要です。', 'wp-update-auto-report' ) ] );
		}

		if ( ! $this->tracker->get_snapshot() ) {
			wp_send_json_error( [ 'message' => __( 'スナップショットが存在しません。STEP 1 でスナップショットを取得してください。', 'wp-update-auto-report' ) ] );
		}

		$diff_items = $this->tracker->get_diff_items();
		if ( empty( $diff_items ) ) {
			wp_send_json_error( [ 'message' => __( 'アップデートがありません。', 'wp-update-auto-report' ) ] );
		}

		$release_notes_fetcher = new WUAR_Release_Notes();
		$release_notes         = $release_notes_fetcher->fetch( $diff_items );

		$ai_reporter = new WUAR_AI_Reporter();
		$report      = $ai_reporter->generate( $diff_items, $release_notes, $this->get_current_ai_model() );

		if ( is_wp_error( $report ) ) {
			wp_send_json_error( [ 'message' => $report->get_error_message() ] );
		}

		wp_send_json_success( [ 'report' => $report ] );
	}

	public function ajax_reset_snapshot(): void {
		check_ajax_referer( 'wuar_network_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( [ 'message' => __( '権限がありません。', 'wp-update-auto-report' ) ] );
		}

		if ( ! $this->tracker->get_snapshot() ) {
			wp_send_json_error( [ 'message' => __( 'スナップショットが存在しません。', 'wp-update-auto-report' ) ] );
		}

		$this->tracker->reset_snapshot();

		if ( $this->tracker->get_snapshot() ) {
			wp_send_json_error( [ 'message' => __( 'スナップショットのリセットに失敗しました。', 'wp-update-auto-report' ) ] );
		}

		wp_send_json_success( [
			'message' => __( 'スナップショットをリセットしました。', 'wp-update-auto-report' ),
		] );
	}

	private function get_snapshot_label(): string {
		$snapshot = $this->tracker->get_snapshot();
		if ( ! $snapshot ) {
			return '';
		}
		return sprintf(
			/* translators: %s: datetime string */
			__( 'スナップショット取得済み（%s）', 'wp-update-auto-report' ),
			$snapshot['recorded_at']
		);
	}
}
