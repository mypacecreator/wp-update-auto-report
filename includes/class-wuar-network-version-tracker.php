<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WUAR_Network_Version_Tracker {

	/**
	 * レポート対象サイトを列挙する（アーカイブ・スパム・削除済みサイトは除外）。
	 *
	 * @return WP_Site[]
	 */
	public function get_target_sites(): array {
		return get_sites( [
			'archived' => 0,
			'spam'     => 0,
			'deleted'  => 0,
		] );
	}

	public function save_snapshot_all_sites(): void {
		foreach ( $this->get_target_sites() as $site ) {
			switch_to_blog( (int) $site->blog_id );
			try {
				( new WUAR_Version_Tracker() )->save_snapshot();
			} finally {
				restore_current_blog();
			}
		}
	}

	public function reset_snapshot_all_sites(): void {
		foreach ( $this->get_target_sites() as $site ) {
			switch_to_blog( (int) $site->blog_id );
			try {
				( new WUAR_Version_Tracker() )->reset_snapshot();
			} finally {
				restore_current_blog();
			}
		}
	}

	/**
	 * 全サイトのスナップショット取得状況を集計する。
	 *
	 * @return array{total_sites:int,snapshot_count:int,latest:?string}
	 */
	public function get_snapshot_status(): array {
		$sites          = $this->get_target_sites();
		$snapshot_count = 0;
		$latest         = null;

		foreach ( $sites as $site ) {
			switch_to_blog( (int) $site->blog_id );
			try {
				$snapshot = ( new WUAR_Version_Tracker() )->get_snapshot();
				if ( $snapshot ) {
					$snapshot_count++;
					if ( null === $latest || $snapshot['recorded_at'] > $latest ) {
						$latest = $snapshot['recorded_at'];
					}
				}
			} finally {
				restore_current_blog();
			}
		}

		return [
			'total_sites'    => count( $sites ),
			'snapshot_count' => $snapshot_count,
			'latest'         => $latest,
		];
	}

	/**
	 * 全サイトを横断した差分アイテムを取得する。各アイテムに対象サイト名を付与する。
	 *
	 * @return array
	 */
	public function get_diff_items_all_sites(): array {
		$diff = [];

		foreach ( $this->get_target_sites() as $site ) {
			switch_to_blog( (int) $site->blog_id );
			try {
				$site_name  = get_bloginfo( 'name' );
				$site_items = ( new WUAR_Version_Tracker() )->get_diff_items();
				foreach ( $site_items as $item ) {
					$item['site'] = $site_name;
					$diff[]       = $item;
				}
			} finally {
				restore_current_blog();
			}
		}

		return $diff;
	}

	public function generate_fixed_report_all_sites(): string {
		$diff   = $this->get_diff_items_all_sites();
		$status = $this->get_snapshot_status();
		$date   = wp_date( 'Y年n月j日' );

		$lines   = [];
		$lines[] = '# 月次システム定期アップデート作業報告書（ネットワーク全体）';
		$lines[] = '';
		$lines[] = '## 実施日: ' . $date;
		$lines[] = '';

		if ( empty( $diff ) ) {
			$lines[] = '### アップデート内容';
			$lines[] = '';
			$lines[] = 'コア・プラグイン・テーマの更新はありませんでした。システムは通常稼働中です。';
			$lines[] = '';
		} else {
			$lines[] = '### アップデート内容';
			$lines[] = '';
			$lines[] = '| 対象サイト | 種別 | 名称 | 更新前 | 更新後 |';
			$lines[] = '|------------|------|------|--------|--------|';
			foreach ( $diff as $item ) {
				$lines[] = sprintf(
					'| %s | %s | %s | %s | %s |',
					$this->escape_md_cell( $item['site'] ?? '' ),
					$this->escape_md_cell( $this->translate_type( $item['type'] ) ),
					$this->escape_md_cell( $item['name'] ),
					$this->escape_md_cell( $item['before'] ),
					$this->escape_md_cell( $item['after'] )
				);
			}
			$lines[] = '';
		}

		$lines[] = '### 作業メモ';
		$lines[] = '';
		$lines[] = sprintf( '- **対象サイト数:** %d', $status['total_sites'] );
		if ( $status['latest'] ) {
			$lines[] = '- **スナップショット取得日時（最終）:** ' . $status['latest'];
		}

		return implode( "\n", $lines );
	}

	private function translate_type( string $type ): string {
		$translations = [
			'Core'   => '本体',
			'Plugin' => 'プラグイン',
			'Theme'  => 'テーマ',
		];
		return $translations[ $type ] ?? $type;
	}

	private function escape_md_cell( string $text ): string {
		return str_replace( '|', '\\|', wp_strip_all_tags( $text ) );
	}
}
