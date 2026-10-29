# Repository Guidelines

## このリポジトリについて

このリポジトリは、社内向けチームチャットアプリの開発用です。要件と設計書に沿ってLaravel 10で実装します。作業を始めるときは、まず以下の決めごとの所在と画面見本を確認してください。

## 決めごとの所在

| 確認する内容 | 場所 |
| --- | --- |
| アプリの要件・スコープ | `docs/spec.md` |
| 機能、確認事項、画面、データ、権限・API、動作、受け入れ条件の設計 | `docs/design/`（一覧は`docs/design/README.md`） |
| 画面構成と表示状態 | `mockup/`（入口は`mockup/index.html`） |
| 色、文字、余白などの画面ルール | `mockup/design-guide.md` |
| PHP・エディターの書式 | `.editorconfig`、`composer.json` |
| テスト設定 | `phpunit.xml` |
| 開発環境・サービス構成 | `compose.yaml`、`.env.example` |

実装と設計書が食い違ったら、**コードではなく `docs/design/` を先に直す**。設計書は提出物の中心で、最後まで実物と一致させる。

## プロジェクト構成

Laravel標準のMVC構成に従って実装してください。

- `docs/spec.md`、`docs/design/`: 要件、構成、受け入れ条件。
- `mockup/index.html`: 画面見本の一覧。ブラウザで直接開けます。

## 環境構築・開発・テストのコマンド

初回は`composer install`でPHPの依存パッケージをインストールし、`.env.example`を`.env`へコピーしてください。Sailを使う前にDockerを起動します。

- `./vendor/bin/sail up -d`: アプリケーションとMySQLのコンテナを起動します。
- `./vendor/bin/sail artisan key:generate`: 初期設定時にアプリケーションキーを生成します。
- `./vendor/bin/sail artisan migrate`: 未適用のマイグレーションを実行します。
- `./vendor/bin/sail npm install`: フロントエンドの依存パッケージをインストールします。
- `./vendor/bin/sail npm run dev`: 開発用のViteサーバーを起動します。
- `./vendor/bin/sail npm run build`: 本番用のアセットをビルドします。
- `./vendor/bin/sail artisan test`: PHPUnitのテストを実行します。
- `./vendor/bin/sail pint`: PHPコードを整形します。

## コーディング規約・命名規則

`.editorconfig`に従い、UTF-8、LF改行、スペース4個のインデント、ファイル末尾の改行を使用してください。YAMLのインデントはスペース2個です。PHPはLaravel Pintで整形します。名前空間とディレクトリをPSR-4に従って対応させてください。クラス名は`PascalCase`、メソッド名は`camelCase`、DBカラム名は`snake_case`にします。マイグレーションは`create_channels_table`のように目的が分かる名前にしてください。

承認済みの画面設計ではJavaScriptを使わず、リンクとフォーム送信で操作します。動作を変更する前に関連する設計書を更新し、実装との整合性を保ってください。図はMarkdown内にMermaidで記述します。

## 守ってほしい書き方

実装を始める前に、変更に関係する次の決めごとを確認してください。内容と理由は参照先にまとめています。

| 実装する箇所 | 決めごとの在りか |
| --- | --- |
| 認可・対象の取得・拒否する順序 | [permissions-api.md「権限マトリクス」](docs/design/permissions-api.md#権限マトリクス)、[behavior.md「例外方針」](docs/design/behavior.md#例外方針) |
| 削除処理・用途ごとの取得条件 | [data.md「削除状態と取得条件」](docs/design/data.md#削除状態と取得条件)、[behavior.md「メッセージの状態遷移」](docs/design/behavior.md#メッセージの状態遷移) |
| 保存する値・一意性・所属・返信の制約 | [data.md「テーブル定義書」](docs/design/data.md#テーブル定義書)、[data.md「保存しない表示項目・集計」](docs/design/data.md#保存しない表示項目集計) |
| トランザクション・同時操作・編集競合 | [behavior.md「更新の整合性と同時操作」](docs/design/behavior.md#更新の整合性と同時操作)、[behavior.md「本文編集の競合確認（Q-30）」](docs/design/behavior.md#本文編集の競合確認q-30) |
| 入力の扱い・検証・エラー文言 | [screens.md「入力チェックとエラーメッセージ」](docs/design/screens.md#入力チェックとエラーメッセージ)、[data.md「テーブル定義書」](docs/design/data.md#テーブル定義書) |
| 検索の比較・強調・ページ分割 | [permissions-api.md「検索用の比較と強調表示（Q-35）」](docs/design/permissions-api.md#検索用の比較と強調表示q-35)、[data.md「削除状態と取得条件」](docs/design/data.md#削除状態と取得条件) |
| 画面操作・表示範囲・フォームの戻り先 | [screens.md「操作・表示の共通方針」](docs/design/screens.md#操作表示の共通方針)、[permissions-api.md「リンクで引き継ぐ表示範囲と戻り先」](docs/design/permissions-api.md#リンクで引き継ぐ表示範囲と戻り先) |
| 公開APIの出力・ページ分割・エラー | [permissions-api.md「公開APIの共通契約」以降](docs/design/permissions-api.md#公開apiの共通契約) |
| 障害応答・ログ・保存後の失敗 | [behavior.md「例外方針」](docs/design/behavior.md#例外方針)、[behavior.md「更新の整合性と同時操作」](docs/design/behavior.md#更新の整合性と同時操作) |

## 変更時の注意

- Laravelコマンドは`./vendor/bin/sail artisan`経由で実行してください。
- 機能を変更したら、関連する機能テストも追加または更新し、`./vendor/bin/sail artisan test`で確認してください。実行できない場合は理由と未確認の範囲を共有してください。
- 変更したPHPは`./vendor/bin/sail pint`で整形してください。
- 実行済みのマイグレーションは編集・削除せず、スキーマ変更には新しいマイグレーションを追加してください。
- `.env`の中身は読まないでください。設定値を確認したいときは`.env.example`を見てください。`.env`を変更せず、認証情報をコミットしないでください。

## テスト方針

PHPUnit 10を使用します。ファイル名は`*Test.php`、メソッド名は検証内容が分かる`test_*`形式にしてください。HTTP、認可、DBを扱うシナリオは機能テスト、独立したロジックは単体テストに配置します。`docs/design/acceptance.md`を基に、入力チェックや権限不足のケースも検証してください。DBを扱うテストには、設定済みの`testing`データベースを使用します。カバレッジの数値目標は設定されていません。チャンネル関連のテストを絞る例は`./vendor/bin/sail artisan test --filter=ChannelTest`です。

## ブランチとプルリクエストの方針

直近の履歴では、`docs: add acceptance criteria and finalize review decisions`のように、接頭辞付きの簡潔な命令形のコミット件名を使用しています。変更内容に合う接頭辞を付け、この形式に従ってください。`.env`や認証情報はコミットしないでください。

イシュー1件につきブランチを1本作り、プルリクエスト1本で受け取ります。イシューに紐づかない修正も、変更の目的ごとにブランチとプルリクエストを分けてください。ブランチ名は次の形式にします。

| 対象 | ブランチ名の形式 | 例 |
| --- | --- | --- |
| イシューに紐づく変更 | `<種別>/<イシュー番号>-<概要>` | `feat/1-authentication` |
| イシューに紐づかない変更 | `<種別>/<概要>` | 物差し・開発設定の修正は `chore/harness-gaps`、設計書だけの修正は `docs/auth-design` |

種別は機能追加なら`feat`、不具合修正なら`fix`、文書だけなら`docs`、開発環境や`.claude/`・`.codex/`の物差しの整備なら`chore`を使います。概要は英小文字・数字・ハイフンで、変更内容が分かる短い名前にしてください。

PR本文には動作の変更内容、検証コマンドとテスト結果、受け入れ条件それぞれの判定を記載します。イシューに紐づく場合は最後に`Closes #<イシュー番号>`を入れてください。紐づかない場合はIssue欄を「該当なし」とし、`Closes`の行は省略します。画面を変更した場合はスクリーンショットを添付し、関連する設計書の更新も含めてください。

マージ後は`main`へ切り替えて`origin/main`と同期し、マージ済みの作業ブランチをリモート・ローカルの両方から削除してください。

PR本文は次の型を使い、各Issueの受け入れ条件に合わせて記入してください。イシューに紐づかない場合は、変更の目的に対応する確認項目と判定を「受け入れ条件の確認」に記載します。

```markdown
## 目的
<!-- 解決する課題と変更の概要 -->

## 変更内容
- <!-- 実装した内容。画面・ルート・DB変更があれば明記 -->

## 関連
- Issue: <!-- #番号 / 該当なし -->
- 設計: <!-- docs/design/ の該当ファイルやID -->

## 受け入れ条件の確認
- <!-- 条件1: 達成 / 未達 / 未確認 — 確認方法・結果 -->
- <!-- 条件2: 達成 / 未達 / 未確認 — 確認方法・結果 -->
- 未達・未確認の条件と理由: <!-- なし / 内容 -->

## テスト
- 実行結果: <!-- コマンド、成功・失敗、結果 -->
- 未実行: <!-- なし / 理由と未確認範囲 -->

## 画面
<!-- UI変更時はスクリーンショットを添付。該当なしなら「該当なし」 -->

Closes #<!-- イシュー番号 -->
```
