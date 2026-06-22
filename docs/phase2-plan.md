# Phase 2 実装計画: AI詳細レポート生成

## 概要

Phase 1 では固定フォーマットの Markdown レポートを生成します。Phase 2 では WordPress 7.0 に標準搭載された **Connectors API**（`wp_ai_client_prompt`）を活用し、各アップデートのリリースノートを取得・要約した「顧客向け詳細レポート」を生成します。

| 比較項目 | Phase 1（実装済み） | Phase 2（本計画） |
|----------|--------------------|--------------------|
| 出力形式 | 固定フォーマット Markdown | AI 生成・リリースノート引用付き Markdown |
| AI 使用 | なし | あり（WordPress 7.0 Connectors API） |
| 必要 WP バージョン | 6.0 以上 | 7.0 以上 |
| リリースノート取得 | なし | WordPress.org API から自動取得 |

---

## 追加する機能

- 管理画面「ツール ＞ WP レポート生成」に **「AI詳細レポートを生成」ボタン**を追加
  - Phase 1 の「レポートを生成する」ボタンの直下に配置
  - WordPress 7.0 未満の環境では非表示（`function_exists('wp_ai_client_prompt')` チェック）
- 生成結果は Phase 1 と同じ Textarea・コピー・ダウンロード UI を流用

---

## 追加ファイル

```
includes/class-wuar-ai-reporter.php      # Connectors API 呼び出し・プロンプト組み立て
includes/class-wuar-release-notes.php   # WordPress.org API からリリースノートを取得・キャッシュ
templates/prompt-ai-report.md          # AI プロンプトのデフォルトテンプレート（運用者が編集可能）
```

### `templates/prompt-ai-report.md`

AIへのプロンプトを記述するテンプレートファイル。運用者が SSH/FTP やテキストエディタで直接書き換えることで、出力スタイルや文体を自由にカスタマイズできます。

- **プレースホルダー:** `{diff_and_notes}`（差分データ＋リリースノートに自動置換される）
- **フォールバック:** ファイルが存在しない場合はコード内のデフォルトテンプレートを使用

デフォルトのテンプレート内容:

```
あなたは優秀なWordPressエンジニアです。
以下のアップデート差分データと各バージョンの公式リリースノートを元に、
クライアント（非技術者）が読んで安心できる丁寧な
「月次システム定期アップデート作業報告書」を日本語のMarkdown形式で作成してください。

各アップデート項目について、リリースノートの内容を参照しながら
なぜこのアップデートが必要だったか、セキュリティ・安定性・機能面での
改善点を非技術者向けに簡潔に日本語で要約して説明してください。
検証結果の項目（フロント表示、フォーム動作等）は、すべて正常に完了した
想定の定型文を含めてください。

--- アップデート差分とリリースノート ---

{diff_and_notes}
```

---

### `class-wuar-release-notes.php`

WordPress.org の公開 API からリリースノートを取得します。

**取得エンドポイント:**

| 種別 | API URL |
|------|---------|
| プラグイン | `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug={slug}&fields=sections` |
| テーマ | `https://api.wordpress.org/themes/info/1.1/?action=theme_information&slug={slug}&fields=sections` |
| コア | `https://api.wordpress.org/core/changelog/1.0/?version={new_version}` |

**主要メソッド:**
- `fetch(array $diff_items): array` — 差分アイテムごとにリリースノートを取得して返す
  - 取得失敗（タイムアウト・非公開プラグイン等）は `null` を返してスキップ
  - 結果は Transient（`wuar_rn_{slug}_{version}`、TTL: 24時間）にキャッシュ（`wp_remote_get()` 使用）

---

### `class-wuar-ai-reporter.php`

**主要メソッド:**
- `load_prompt_template(): string` — `templates/prompt-ai-report.md` を読み込む。ファイルが存在しない場合はハードコードされたフォールバックを返す
- `generate(array $diff_items, array $release_notes): string|WP_Error`
  1. `function_exists('wp_ai_client_prompt')` チェック → なければ `WP_Error` を返す
  2. `load_prompt_template()` でテンプレートを取得
  3. 差分アイテムとリリースノートを `{diff_and_notes}` プレースホルダーに展開
  4. `wp_ai_client_prompt($prompt)->generate_text()` を呼び出す
  5. 失敗時は `WP_Error` を返す

---

## 既存ファイルへの変更

### `includes/class-wuar-admin.php`

- `wp_ajax_wuar_generate_ai_report` AJAX アクションを追加
  - `class-wuar-release-notes.php` でリリースノートを取得
  - `class-wuar-ai-reporter.php` で AI レポートを生成
  - 生成後に `reset_snapshot()` を呼び出してスナップショットを更新
- `render_page()` に「AI詳細レポートを生成」ボタンを追加（WP 7.0 未満では非表示）
- `enqueue_assets()` の `wuarData` に `hasAiSupport` フラグを追加

### `assets/js/wuar-admin.js`

- `#wuar-ai-generate-btn` クリックハンドラを追加（ローディング → AJAX → Textarea 更新）

---

## 必要環境

- WordPress 7.0 以上（Connectors API 搭載）
- 管理画面「設定 ＞ コネクタ」で AI モデル（Claude 3.5 Haiku 等）を事前に設定済みであること

## 実装方針

- WordPress 7.0 はリリース済みのため、いつでも着手可能
- Phase 1 との後方互換性を維持（WP 6.x でも Phase 1 機能は引き続き動作する）
- Phase 2 機能は WP 7.0 環境でのみ有効化（それ以外では UI 上で非表示）
