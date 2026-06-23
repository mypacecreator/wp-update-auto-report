<?php
/**
 * Plugin Name:       WP Update Auto Report
 * Plugin URI:        https://github.com/mypacecreator/wp-update-auto-report
 * Description:       WordPress コア・プラグイン・テーマのアップデート差分を検知し、クライアント提出用の月次作業報告書を自動生成します。
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            mypacecreator
 * Author URI:        https://github.com/mypacecreator
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-update-auto-report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WUAR_VERSION', '1.0.0' );
define( 'WUAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WUAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * AI モデル設定 — モデルの追加・変更・デフォルト変更はここだけ編集する
 *
 * WUAR_AI_MODELS: [ モデル ID => 表示ラベル ] の連想配列
 * WUAR_AI_MODEL_DEFAULT: WUAR_AI_MODELS のいずれかのキーを指定
 */
define( 'WUAR_AI_MODELS', [
	'claude-haiku-4-5-20251001'  => 'Claude Haiku 4.5',
	'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
	'claude-sonnet-4-6'          => 'Claude Sonnet 4.6',
	'claude-opus-4-8'            => 'Claude Opus 4.8',
] );
define( 'WUAR_AI_MODEL_DEFAULT', 'claude-haiku-4-5-20251001' );

require_once WUAR_PLUGIN_DIR . 'includes/class-wuar-version-tracker.php';
require_once WUAR_PLUGIN_DIR . 'includes/class-wuar-admin.php';
require_once WUAR_PLUGIN_DIR . 'includes/class-wuar-release-notes.php';
require_once WUAR_PLUGIN_DIR . 'includes/class-wuar-ai-reporter.php';

final class WP_Update_Auto_Report {

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		load_plugin_textdomain( 'wp-update-auto-report', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		new WUAR_Admin();
	}
}

add_action( 'plugins_loaded', [ 'WP_Update_Auto_Report', 'get_instance' ] );
