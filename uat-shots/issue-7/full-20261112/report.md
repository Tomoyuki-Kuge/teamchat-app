実施日：2026-11-12（日本時間）

# Issue #7 公開API：全体受け入れUAT

対象：`chore/handover`、基準コミット`a91cf2e`と既存の設計書修正差分。Playwright MCPで http://localhost を1280×900pxから操作した。[受け入れ条件](../../../docs/design/acceptance.md)の「確認の前提」に従い、佐藤・鈴木・高橋・登録確認・未ログインを独立したブラウザセッションで確認した。鈴木の新着確認用の2タブは同じセッションを共有する。

| 条件 | 判定 | 画面で観測した根拠・未確認範囲 | スクリーンショット |
| --- | --- | --- | --- |
| AC-7-1 | 満たす | 未ログイン独立セッションで/api/channelsはHTTP200、data/meta/linksあり。作成順ID[1,2,3,7]、per_page=10・total=4・until_id=7・last_page=1。プライベートID4/6/8なし。10件超のチャンネル境界は本UATでは未確認。 | [01_guest-channels](01_guest-channels.png) |
| AC-7-2 | 満たす | 未ログインで公開ID7のメッセージAPI。1ページ目は通常投稿[65,64,63,62,61]と返信[70,69,57,56,55]の別配列で各5件。新しい日時・同時刻ID降順、until_id=70・通常投稿総数8・返信総数5。2ページ目は通常投稿[60,59,58]・返信[]。削除親54は返さず未削除返信は返る。 | [02_messages-five-each](02_messages-five-each.png)・[03_messages-second-page](03_messages-second-page.png) |
| AC-7-3 | 満たす | さらに別の新規未ログインセッションで、一覧を経由せず/api/channels/4/messagesを直接開く。HTTP404、error.code=NOT_FOUND・message=対象が見つかりません・details={}のみ。名前・本文なし。 | [04_private-direct-404](04_private-direct-404.png) |
| AC-7-4 | 満たす | 佐藤の通常一覧には作成者の採用プロジェクトとID6を表示。同じログインセッションの/api/channelsは公開[1,2,3,7]だけで、未ログイン時の表示JSON文字列と完全一致。 | [05_sato-private-visible](05_sato-private-visible.png)・[06_sato-public-api](06_sato-public-api.png) |

公開APIのJSONはブラウザに表示された本文を解析してID・件数・並び順を確認した。画像はブラウザの生のJSON表示（日本語はUnicodeエスケープ）で、内容を加工していない。AC-7-3はAPI一覧を開いた未ログインセッションとも別の新規セッションから直接アクセスした。HEAD・他メソッド・CORS・障害応答やチャンネル10件超の境界は本UATでは未確認。

## 後片付け・確認範囲

[全体報告](../../full-acceptance-20261112/report.md#後片付け)に作成物と最終状態を記録した。確認用チャンネルID5・6・7は佐藤、ID8は高橋が本人の削除確認フォームから削除済み。既存の初期チャンネル・投稿・所属を変更・削除していない。新規登録アカウント`full-uat-20261112@example.com`（受入確認1112）は削除画面がないため残存し、ログアウト済み。

アプリのコード・設定は変更していない。本UATでは自動テスト・Pintを再実行しておらず、サーバー内部の保存値や全組み合わせの検証を画面結果へ混ぜていない。内部スクロールのある画面ではfull-page画像に全行が入らない場合があるため、画面のDOMのID・本文・件数も併用した。
