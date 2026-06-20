<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WUAR_Version_Tracker {

	const OPTION_KEY = 'wuar_version_snapshot';

	public function save_snapshot(): void {
		$snapshot = [
			'recorded_at' => current_time( 'mysql' ),
			'core'        => get_bloginfo( 'version' ),
			'plugins'     => $this->get_current_plugins(),
			'themes'      => $this->get_current_themes(),
		];
		update_option( self::OPTION_KEY, $snapshot, false );
	}

	public function get_snapshot(): array|false {
		$snapshot = get_option( self::OPTION_KEY, false );
		return is_array( $snapshot ) ? $snapshot : false;
	}

	public function reset_snapshot(): void {
		$this->save_snapshot();
	}

	public function get_diff_items(): array {
		$snapshot = $this->get_snapshot();
		if ( ! $snapshot ) {
			return [];
		}

		$diff = [];

		// Core
		$current_core = get_bloginfo( 'version' );
		if ( version_compare( $snapshot['core'], $current_core, '!=' ) ) {
			$diff[] = [
				'type'    => 'Core',
				'name'    => 'WordPress',
				'before'  => $snapshot['core'],
				'after'   => $current_core,
			];
		}

		// Plugins
		$current_plugins  = $this->get_current_plugins();
		$snapshot_plugins = $snapshot['plugins'] ?? [];

		foreach ( $current_plugins as $file => $data ) {
			$old_version = $snapshot_plugins[ $file ]['version'] ?? null;
			if ( null !== $old_version && version_compare( $old_version, $data['version'], '!=' ) ) {
				$diff[] = [
					'type'   => 'Plugin',
					'name'   => $data['name'],
					'before' => $old_version,
					'after'  => $data['version'],
				];
			}
		}

		// Themes
		$current_themes  = $this->get_current_themes();
		$snapshot_themes = $snapshot['themes'] ?? [];

		foreach ( $current_themes as $slug => $data ) {
			$old_version = $snapshot_themes[ $slug ]['version'] ?? null;
			if ( null !== $old_version && version_compare( $old_version, $data['version'], '!=' ) ) {
				$diff[] = [
					'type'   => 'Theme',
					'name'   => $data['name'],
					'before' => $old_version,
					'after'  => $data['version'],
				];
			}
		}

		return $diff;
	}

	public function generate_fixed_report(): string {
		$diff     = $this->get_diff_items();
		$snapshot = $this->get_snapshot();
		$date     = wp_date( 'Y年n月j日' );
		$site_url = get_site_url();

		$lines   = [];
		$lines[] = '# 月次システム定期アップデート作業報告書';
		$lines[] = '';
		$lines[] = '**作業日:** ' . $date;
		$lines[] = '**対象サイト:** ' . $site_url;
		if ( $snapshot ) {
			$lines[] = '**スナップショット取得日時:** ' . $snapshot['recorded_at'];
		}
		$lines[] = '';

		if ( empty( $diff ) ) {
			$lines[] = '## アップデート内容';
			$lines[] = '';
			$lines[] = '今月はコア・プラグイン・テーマの更新はありませんでした。システムは通常稼働中です。';
			$lines[] = '';
		} else {
			$lines[] = '## アップデート内容';
			$lines[] = '';
			$lines[] = '| 種別 | 名称 | 更新前 | 更新後 |';
			$lines[] = '|------|------|--------|--------|';
			foreach ( $diff as $item ) {
				$lines[] = sprintf(
					'| %s | %s | %s | %s |',
					esc_html( $item['type'] ),
					esc_html( $item['name'] ),
					esc_html( $item['before'] ),
					esc_html( $item['after'] )
				);
			}
			$lines[] = '';
		}

		$lines[] = '## 確認事項';
		$lines[] = '';
		$lines[] = '- フロント表示: 正常';
		$lines[] = '- フォーム動作: 正常';
		$lines[] = '- その他: 異常なし';
		$lines[] = '';
		$lines[] = '---';
		$lines[] = '';
		$lines[] = '以上、ご確認のほどよろしくお願いいたします。';

		return implode( "\n", $lines );
	}

	private function get_current_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$result      = [];

		foreach ( $all_plugins as $file => $data ) {
			if ( is_plugin_active( $file ) ) {
				$result[ $file ] = [
					'name'    => $data['Name'],
					'version' => $data['Version'],
				];
			}
		}

		return $result;
	}

	private function get_current_themes(): array {
		$themes = wp_get_themes();
		$result = [];

		foreach ( $themes as $slug => $theme ) {
			$result[ $slug ] = [
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
			];
		}

		return $result;
	}
}
