/* global wuarNetworkData */
( function () {
	'use strict';

	const snapshotBtn     = document.getElementById( 'wuar-snapshot-btn' );
	const snapshotMsg     = document.getElementById( 'wuar-snapshot-msg' );
	const generateBtn     = document.getElementById( 'wuar-generate-btn' );
	const loadingEl       = document.getElementById( 'wuar-loading' );
	const resultArea      = document.getElementById( 'wuar-result-area' );
	const resultTA        = document.getElementById( 'wuar-result' );
	const copyBtn         = document.getElementById( 'wuar-copy-btn' );
	const downloadBtn     = document.getElementById( 'wuar-download-btn' );
	const resetSnapshotBtn = document.getElementById( 'wuar-reset-snapshot-btn' );
	const resetMsg        = document.getElementById( 'wuar-reset-msg' );

	// ---- Snapshot --------------------------------------------------------

	if ( snapshotBtn ) {
		snapshotBtn.addEventListener( 'click', async () => {
			snapshotBtn.disabled = true;
			snapshotMsg.hidden = false;
			snapshotMsg.textContent = '保存中...';

			try {
				const data = await post( 'wuar_network_save_snapshot' );
				showSnapshotStatus( data.label );
				snapshotMsg.textContent = '✓ 保存しました';

				if ( generateBtn ) {
					generateBtn.disabled = false;
				}
			} catch ( err ) {
				snapshotMsg.textContent = 'エラー: ' + err.message;
			} finally {
				snapshotBtn.disabled = false;
			}
		} );
	}

	function showSnapshotStatus( label ) {
		const statusEl = document.querySelector( '.wuar-snapshot-status' );
		if ( statusEl ) {
			statusEl.innerHTML =
				'<span class="dashicons dashicons-yes-alt" style="color:#46b450;vertical-align:middle;"></span> ' +
				escHtml( label );
		}
	}

	// ---- Generate --------------------------------------------------------

	if ( generateBtn ) {
		generateBtn.addEventListener( 'click', async () => {
			generateBtn.disabled = true;
			loadingEl.hidden = false;
			resultArea.hidden = true;

			try {
				const data = await post( 'wuar_network_generate_report' );
				resultTA.value = data.report;
				resultArea.hidden = false;
			} catch ( err ) {
				alert( 'レポート生成に失敗しました: ' + err.message );
			} finally {
				generateBtn.disabled = false;
				loadingEl.hidden = true;
			}
		} );
	}

	// ---- AI Generate -----------------------------------------------------

	const aiGenerateBtn = document.getElementById( 'wuar-ai-generate-btn' );

	if ( aiGenerateBtn ) {
		aiGenerateBtn.addEventListener( 'click', async () => {
			aiGenerateBtn.disabled = true;
			loadingEl.hidden = false;
			resultArea.hidden = true;

			try {
				const data = await post( 'wuar_network_generate_ai_report' );
				resultTA.value = data.report;
				resultArea.hidden = false;
			} catch ( err ) {
				alert( 'AI レポート生成に失敗しました: ' + err.message );
			} finally {
				aiGenerateBtn.disabled = false;
				loadingEl.hidden = true;
			}
		} );
	}

	// ---- Reset Snapshot --------------------------------------------------

	if ( resetSnapshotBtn ) {
		resetSnapshotBtn.addEventListener( 'click', async () => {
			if ( ! confirm( 'ネットワーク内の全サイトのスナップショットをリセットしますか？\n\nリセット後は現在の差分情報が失われます。レポートを生成済みであることを確認してください。' ) ) {
				return;
			}

			resetSnapshotBtn.disabled = true;
			resetMsg.hidden = false;
			resetMsg.textContent = 'リセット中...';

			try {
				const data = await post( 'wuar_network_reset_snapshot' );
				resetMsg.textContent = '✓ ' + data.message;

				// Keep button disabled until reload
				setTimeout( () => {
					location.reload();
				}, 2000 );

			} catch ( err ) {
				resetMsg.textContent = 'エラー: ' + err.message;
				resetSnapshotBtn.disabled = false;
			}
		} );
	}

	// ---- Copy (ClipboardItem — rich text) --------------------------------

	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', async () => {
			const md   = resultTA.value;
			const html = markdownToHtml( md );

			try {
				// ClipboardItem API でリッチテキスト＋プレーンテキストを書き込む
				await navigator.clipboard.write( [
					new ClipboardItem( {
						'text/html':  new Blob( [ html ], { type: 'text/html' } ),
						'text/plain': new Blob( [ md ],   { type: 'text/plain' } ),
					} ),
				] );
				flashBtn( copyBtn, 'コピーしました！', 'リッチテキストでコピー' );
			} catch {
				// ClipboardItem 失敗 → Clipboard API のプレーンテキストで再試行
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					try {
						await navigator.clipboard.writeText( md );
						flashBtn( copyBtn, 'コピーしました（テキスト）', 'リッチテキストでコピー' );
					} catch {
						execCommandFallback();
					}
				} else {
					// Clipboard API 自体が存在しない環境
					execCommandFallback();
				}
			}

			function execCommandFallback() {
				try {
					resultTA.select();
					const ok = document.execCommand( 'copy' );
					if ( ok ) {
						flashBtn( copyBtn, 'コピーしました（テキスト）', 'リッチテキストでコピー' );
					} else {
						alert( 'コピーできませんでした。テキストエリアを手動で選択してコピーしてください。' );
					}
				} catch {
					alert( 'コピーできませんでした。テキストエリアを手動で選択してコピーしてください。' );
				}
			}
		} );
	}

	// ---- Download --------------------------------------------------------

	if ( downloadBtn ) {
		downloadBtn.addEventListener( 'click', () => {
			const blob = new Blob( [ resultTA.value ], { type: 'text/markdown; charset=utf-8' } );
			const url  = URL.createObjectURL( blob );
			const a    = Object.assign( document.createElement( 'a' ), {
				href:     url,
				download: 'network_report_' + wuarNetworkData.today + '.md',
			} );
			document.body.appendChild( a );
			a.click();
			document.body.removeChild( a );
			URL.revokeObjectURL( url );
		} );
	}

	// ---- Helpers ---------------------------------------------------------

	async function post( action ) {
		const body = new URLSearchParams( {
			action,
			_ajax_nonce: wuarNetworkData.nonce,
		} );
		const res  = await fetch( wuarNetworkData.ajaxUrl, { method: 'POST', body } );
		const json = await res.json();
		if ( ! json.success ) {
			throw new Error( json.data?.message ?? '不明なエラー' );
		}
		return json.data;
	}

	function flashBtn( btn, tempLabel, originalLabel ) {
		btn.textContent = tempLabel;
		setTimeout( () => {
			btn.textContent = originalLabel;
		}, 2000 );
	}

	function escHtml( str ) {
		return str
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/**
	 * 固定フォーマットの Markdown を HTML に変換する軽量実装。
	 * 対応: h1〜h3、**bold**、GFM テーブル、- リスト、段落。
	 */
	function markdownToHtml( md ) {
		const lines   = md.split( '\n' );
		const out     = [];
		let inTable   = false;
		let inList    = false;
		let tableRows = [];

		function splitTableRow( row ) {
			// バックスラッシュでエスケープされた `\|` は区切りとして扱わず、リテラルの `|` に戻す
			return row.split( /(?<!\\)\|/ ).slice( 1, -1 ).map(
				( c ) => c.trim().replace( /\\\|/g, '|' )
			);
		}

		function flushTable() {
			if ( ! tableRows.length ) return;
			const [ header, , ...body ] = tableRows;
			const th = splitTableRow( header ).map(
				( c ) => '<th>' + inline( c ) + '</th>'
			).join( '' );
			const trs = body.map(
				( row ) => '<tr>' + splitTableRow( row ).map(
					( c ) => '<td>' + inline( c ) + '</td>'
				).join( '' ) + '</tr>'
			).join( '' );
			out.push( '<table border="1" cellpadding="4" cellspacing="0"><thead><tr>' + th + '</tr></thead><tbody>' + trs + '</tbody></table>' );
			tableRows = [];
			inTable = false;
		}

		function flushList() {
			if ( ! inList ) return;
			out.push( '</ul>' );
			inList = false;
		}

		for ( const raw of lines ) {
			const line = raw;

			// Heading
			const h = line.match( /^(#{1,3})\s+(.+)$/ );
			if ( h ) {
				flushTable();
				flushList();
				const level = h[ 1 ].length;
				out.push( `<h${level}>${inline( h[ 2 ] )}</h${level}>` );
				continue;
			}

			// HR
			if ( /^---+$/.test( line.trim() ) ) {
				flushTable();
				flushList();
				out.push( '<hr>' );
				continue;
			}

			// Table row
			if ( line.trimStart().startsWith( '|' ) ) {
				flushList();
				inTable = true;
				tableRows.push( line );
				continue;
			} else if ( inTable ) {
				flushTable();
			}

			// List item
			const li = line.match( /^- (.+)$/ );
			if ( li ) {
				if ( ! inList ) {
					out.push( '<ul>' );
					inList = true;
				}
				out.push( '<li>' + inline( li[ 1 ] ) + '</li>' );
				continue;
			} else if ( inList ) {
				flushList();
			}

			// Empty line
			if ( line.trim() === '' ) {
				out.push( '' );
				continue;
			}

			// Paragraph
			out.push( '<p>' + inline( line ) + '</p>' );
		}

		flushTable();
		flushList();

		return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' +
			out.join( '\n' ) +
			'</body></html>';
	}

	function inline( text ) {
		return text
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
			.replace( /\*(.+?)\*/g, '<em>$1</em>' )
			.replace( /`(.+?)`/g, '<code>$1</code>' );
	}
}() );
