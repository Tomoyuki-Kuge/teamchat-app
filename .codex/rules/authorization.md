# 認可と対象確認のルール

## 対象

- `app/Policies/**/*.php`
- `app/Http/**/*.php`
- `app/Actions/**/*.php`
- `app/Support/Messages/**/*.php`
- `app/Providers/AuthServiceProvider.php`

## 実装時の判断

- 一覧・検索の閲覧対象の絞り込みと、個別操作のPolicy認可を両方行う。画面の操作ボタンを隠すだけで認可を済ませない。
- チャンネルを閲覧できるか確認してから、本人・作成者の操作権限を判定する。未参加プライベートの存在を拒否応答やエラー画面から知らせない。
- 投稿・返信などの子の対象は、閲覧を確認したチャンネルの関連から取得し、同一チャンネル・親子関係を確認する。
- 対象不存在・関係不整合・本人制限・削除状態・入力不正が重なった場合も、設計の拒否順序を守る。形式不正のIDと、形式が正しいが存在しないIDを区別する。
- 操作可否の条件はPolicyへ集約し、FormRequestと保存Actionから同じ認可を呼ぶ。公開APIには公開APIの対象条件を適用する。
- 保存時はロック後の現在値で閲覧・操作権限を再確認する。リクエスト開始時の認可や取得済みモデルだけに依存しない。
- 入力エラー・編集競合の再表示でも現在の閲覧権限と削除状態を確認する。権限を失った利用者へ保存済み本文や送信した旧本文を再表示しない。
- Policyを追加・変更した場合は、既存の`AuthServiceProvider`の登録と呼び出すabilityの対応を確認する。

## 設計の参照先

- [permissions-api.md「権限マトリクス」](../../docs/design/permissions-api.md#権限マトリクス)
- [behavior.md「例外方針」](../../docs/design/behavior.md#例外方針)
- [behavior.md「更新の整合性と同時操作」](../../docs/design/behavior.md#更新の整合性と同時操作)
- [behavior.md「構成」](../../docs/design/behavior.md#構成)
