<?php
/**
 * WordPress.org API からリリースノートを取得するクラス
 *
 * @package WP_Update_Auto_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WUAR_Release_Notes
 */
class WUAR_Release_Notes {

	/**
	 * API タイムアウト (秒)
	 */
	const API_TIMEOUT = 10;

	/**
	 * キャッシュ期間 (秒)
	 */
	const CACHE_DURATION = 24 * HOUR_IN_SECONDS;

	/**
	 * 差分アイテムからリリースノートを取得
	 *
	 * @param array $diff_items 差分アイテム配列
	 * @return array リリースノート配列 (type => (slug => notes))
	 */
	public function fetch( array $diff_items ): array {
		$release_notes = [
			'Core'   => [],
			'Plugin' => [],
			'Theme'  => [],
		];

		foreach ( $diff_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$type = $item['type'] ?? '';
			$name = $item['name'] ?? '';
			$to   = $item['after'] ?? '';

			if ( empty( $type ) || empty( $name ) || empty( $to ) ) {
				continue;
			}

			if ( ! isset( $release_notes[ $type ] ) ) {
				continue;
			}

			// プラグイン・テーマは name から slug を推測
			if ( 'Core' === $type ) {
				$slug = 'wordpress';
			} else {
				$slug = $this->guess_slug( $name );
			}

			$notes = $this->get_release_note( $type, $slug, $to );
			$release_notes[ $type ][ $name ] = $notes;
		}

		return $release_notes;
	}

	/**
	 * name から slug を推測
	 *
	 * @param string $name プラグイン・テーマ名
	 * @return string 推測した slug
	 */
	private function guess_slug( string $name ): string {
		// 簡易的に lowercase + スペース→ハイフン変換
		return sanitize_title( $name );
	}

	/**
	 * リリースノートを取得 (Transient キャッシュ付き)
	 *
	 * @param string $type タイプ (Core/Plugin/Theme)
	 * @param string $slug スラッグ
	 * @param string $version バージョン
	 * @return string|null リリースノート (取得失敗時は null)
	 */
	private function get_release_note( string $type, string $slug, string $version ): ?string {
		$cache_key = $this->get_cache_key( $type, $slug, $version );
		$cached    = get_transient( $cache_key );

		// キャッシュヒット
		if ( false !== $cached ) {
			return '__NOT_FOUND__' === $cached ? null : $cached;
		}

		// API リクエスト
		$notes = $this->fetch_from_api( $type, $slug, $version );

		// キャッシュ保存 (失敗時も __NOT_FOUND__ として保存)
		set_transient(
			$cache_key,
			null === $notes ? '__NOT_FOUND__' : $notes,
			self::CACHE_DURATION
		);

		return $notes;
	}

	/**
	 * WordPress.org API からリリースノートを取得
	 *
	 * @param string $type タイプ (Core/Plugin/Theme)
	 * @param string $slug スラッグ
	 * @param string $version バージョン
	 * @return string|null リリースノート (取得失敗時は null)
	 */
	private function fetch_from_api( string $type, string $slug, string $version ): ?string {
		$url = $this->get_api_url( $type, $slug, $version );

		if ( ! $url ) {
			return null;
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout' => self::API_TIMEOUT,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		return $this->extract_notes( $type, $data, $version );
	}

	/**
	 * API URL を取得
	 *
	 * @param string $type タイプ (Core/Plugin/Theme)
	 * @param string $slug スラッグ
	 * @param string $version バージョン
	 * @return string|null API URL (取得失敗時は null)
	 */
	private function get_api_url( string $type, string $slug, string $version ): ?string {
		switch ( $type ) {
			case 'Core':
				return sprintf(
					'https://api.wordpress.org/core/changelog/1.0/?version=%s',
					urlencode( $version )
				);

			case 'Plugin':
				return sprintf(
					'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=%s&request[fields][sections]=1&format=json',
					urlencode( $slug )
				);

			case 'Theme':
				return sprintf(
					'https://api.wordpress.org/themes/info/1.1/?action=theme_information&request[slug]=%s&request[fields][sections]=1&format=json',
					urlencode( $slug )
				);

			default:
				return null;
		}
	}

	/**
	 * API レスポンスからリリースノートを抽出
	 *
	 * @param string $type タイプ (Core/Plugin/Theme)
	 * @param array  $data API レスポンスデータ
	 * @param string $version バージョン
	 * @return string|null リリースノート (取得失敗時は null)
	 */
	private function extract_notes( string $type, array $data, string $version ): ?string {
		if ( 'Core' === $type ) {
			// Core changelog API はバージョンをキーとした連想配列を返す
			if ( isset( $data[ $version ]['content'] ) ) {
				return wp_strip_all_tags( $data[ $version ]['content'] );
			}
			// フォールバック: 先頭要素を参照
			$first = reset( $data );
			if ( is_array( $first ) && isset( $first['content'] ) ) {
				return wp_strip_all_tags( $first['content'] );
			}
			return null;
		}

		// Plugin/Theme は sections.changelog
		if ( ! isset( $data['sections']['changelog'] ) ) {
			return null;
		}

		return wp_strip_all_tags( $data['sections']['changelog'] );
	}

	/**
	 * キャッシュキーを生成
	 *
	 * @param string $type タイプ
	 * @param string $slug スラッグ
	 * @param string $version バージョン
	 * @return string キャッシュキー
	 */
	private function get_cache_key( string $type, string $slug, string $version ): string {
		// sanitize_key() はドットを除去するため md5 でハッシュ化してキー衝突を防ぐ
		return sprintf(
			'wuar_rn_%s_%s_%s',
			strtolower( $type ),
			sanitize_key( $slug ),
			md5( $version )
		);
	}
}
