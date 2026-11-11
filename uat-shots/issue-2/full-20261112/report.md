実施日：2026-11-12（日本時間）

# Issue #2 チャンネル：全体受け入れUAT

対象：`chore/handover`、基準コミット`a91cf2e`と既存の設計書修正差分。Playwright MCPで http://localhost を1280×900pxから操作した。[受け入れ条件](../../../docs/design/acceptance.md)の「確認の前提」に従い、佐藤・鈴木・高橋・登録確認・未ログインを独立したブラウザセッションで確認した。鈴木の新着確認用の2タブは同じセッションを共有する。

| 条件 | 判定 | 画面で観測した根拠・未確認範囲 | スクリーンショット |
| --- | --- | --- | --- |
| AC-2-1 | 満たす | 佐藤が名前・説明・プライベートを指定してID5作成。作成直後の会話で本人の本文・佐藤 太郎・2026/11/12 07:25を表示。実投稿まで確認。 | [01_create-and-post](01_create-and-post.png) |
| AC-2-2 | 満たす | ID5の名前を全体受入1112編集後、説明を説明を変更した全体受入確認へ保存。プライベート表示を保持。 | [02_edit-keeps-private](02_edit-keeps-private.png) |
| AC-2-3 | 満たす | 空のチャンネル名でチャンネル名を入力してください。required=true、checkValidity=false、validationMessage=このフィールドを入力してください。作成前後の一覧5件のまま。 | [03_empty-channel-name](03_empty-channel-name.png) |
| AC-2-4 | 満たす | 本人が確認名を入力してID5削除。通常投稿1件・返信0件の確認後/channelsへ戻り一覧5→4件。旧URLはHTTP404、対象が見つかりません。 | [04_delete-confirmation](04_delete-confirmation.png)・[05_deleted-list](05_deleted-list.png)・[06_deleted-url](06_deleted-url.png) |

## 後片付け・確認範囲

[全体報告](../../full-acceptance-20261112/report.md#後片付け)に作成物と最終状態を記録した。確認用チャンネルID5・6・7は佐藤、ID8は高橋が本人の削除確認フォームから削除済み。既存の初期チャンネル・投稿・所属を変更・削除していない。新規登録アカウント`full-uat-20261112@example.com`（受入確認1112）は削除画面がないため残存し、ログアウト済み。

アプリのコード・設定は変更していない。本UATでは自動テスト・Pintを再実行しておらず、サーバー内部の保存値や全組み合わせの検証を画面結果へ混ぜていない。内部スクロールのある画面ではfull-page画像に全行が入らない場合があるため、画面のDOMのID・本文・件数も併用した。
