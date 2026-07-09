<?php
/**
 * Plugin Name: MU Plugins Loader
 * Description: mu-plugins ディレクトリのサブディレクトリに置いたプラグインを読み込むローダー
 * Version: 1.0.0
 * Author: Kei Nomura
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 読み込むMU Pluginのパス配列
 * 新しいプラグインを追加する場合は、ここに相対パスを追加するだけでOK
 */
$mu_plugins_to_load = [
	'/wp-update-auto-report/wp-update-auto-report.php',
];

// プラグインの読み込み
foreach ( $mu_plugins_to_load as $plugin_path ) {
	$full_path = trailingslashit( __DIR__ ) . ltrim( $plugin_path, '/' );
	if ( file_exists( $full_path ) ) {
		require_once $full_path;
	}
}
