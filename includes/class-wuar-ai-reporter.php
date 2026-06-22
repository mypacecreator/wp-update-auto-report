<?php
/**
 * WordPress 7.0 Connectors API を使って AI レポートを生成するクラス
 *
 * @package WP_Update_Auto_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WUAR_AI_Reporter
 */
class WUAR_AI_Reporter {

	/**
	 * プロンプトテンプレートファイルパス
	 */
	const TEMPLATE_PATH = 'templates/prompt-ai-report.md';

	/**
	 * AI レポートを生成
	 *
	 * @param array $diff_items 差分アイテム配列
	 * @param array $release_notes リリースノート配列
	 * @return string|WP_Error 生成されたレポート、またはエラー
	 */
	public function generate( array $diff_items, array $release_notes ) {
		// WP 7.0 チェック
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'wuar_ai_not_supported',
				__( 'AI レポート生成には WordPress 7.0 以上が必要です。', 'wp-update-auto-report' )
			);
		}

		try {
			// プロンプトテンプレート読み込み
			$template = $this->load_template();

			// {diff_and_notes} プレースホルダーに展開
			$diff_and_notes = $this->build_diff_and_notes( $diff_items, $release_notes );
			$prompt         = str_replace( '{diff_and_notes}', $diff_and_notes, $template );

			// Connectors API 呼び出し
			$ai_client = wp_ai_client_prompt( $prompt );
			$result    = $ai_client->generate_text();

			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					'wuar_ai_generation_failed',
					sprintf(
						/* translators: %s: エラーメッセージ */
						__( 'AI レポート生成に失敗しました: %s', 'wp-update-auto-report' ),
						$result->get_error_message()
					)
				);
			}

			return $result;

		} catch ( Exception $e ) {
			return new WP_Error(
				'wuar_ai_exception',
				sprintf(
					/* translators: %s: 例外メッセージ */
					__( 'AI レポート生成中にエラーが発生しました: %s', 'wp-update-auto-report' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * プロンプトテンプレートを読み込み
	 *
	 * @return string テンプレート文字列
	 */
	private function load_template(): string {
		$template_file = plugin_dir_path( dirname( __FILE__ ) ) . self::TEMPLATE_PATH;

		if ( file_exists( $template_file ) ) {
			$content = file_get_contents( $template_file );
			if ( false !== $content ) {
				return $content;
			}
		}

		// ファイル不在時はデフォルトテンプレートを使用
		return $this->get_default_template();
	}

	/**
	 * デフォルトプロンプトテンプレートを取得
	 *
	 * @return string デフォルトテンプレート
	 */
	private function get_default_template(): string {
		return <<<'TEMPLATE'
あなたは優秀なWordPressエンジニアです。
以下のアップデート差分データと各バージョンの公式リリースノートを元に、
クライアント(非技術者)が読んで安心できる丁寧な
「月次システム定期アップデート作業報告書」を日本語のMarkdown形式で作成してください。

各アップデート項目について、リリースノートの内容を参照しながら
なぜこのアップデートが必要だったか、セキュリティ・安定性・機能面での
改善点を非技術者向けに簡潔に日本語で要約して説明してください。
検証結果の項目(フロント表示、フォーム動作等)は、すべて正常に完了した
想定の定型文を含めてください。

--- アップデート差分とリリースノート ---

{diff_and_notes}
TEMPLATE;
	}

	/**
	 * 差分データとリリースノートを結合して展開用文字列を生成
	 *
	 * @param array $diff_items 差分アイテム配列
	 * @param array $release_notes リリースノート配列
	 * @return string 展開用文字列
	 */
	private function build_diff_and_notes( array $diff_items, array $release_notes ): string {
		$lines = [];

		foreach ( $diff_items as $type => $items ) {
			if ( ! is_array( $items ) || empty( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$name = $item['name'] ?? '';
				$from = $item['from'] ?? '';
				$to   = $item['to'] ?? '';

				if ( empty( $name ) || empty( $to ) ) {
					continue;
				}

				// ヘッダー行
				$lines[] = sprintf(
					'【%s】%s: %s -> %s',
					$type,
					$name,
					$from ?: '(新規)',
					$to
				);

				// リリースノート取得
				$notes = $release_notes[ $type ][ $name ] ?? null;

				if ( null === $notes ) {
					$lines[] = 'リリースノート: (取得できませんでした)';
				} else {
					// 長いリリースノートは最初の10行に制限
					$note_lines = explode( "\n", $notes );
					$note_lines = array_slice( $note_lines, 0, 10 );
					$lines[]    = 'リリースノート:';
					$lines[]    = implode( "\n", $note_lines );

					if ( count( explode( "\n", $notes ) ) > 10 ) {
						$lines[] = '(以下略)';
					}
				}

				$lines[] = ''; // 空行
			}
		}

		return implode( "\n", $lines );
	}
}
