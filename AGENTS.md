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

## 変更時の注意

- Laravelコマンドは`./vendor/bin/sail artisan`経由で実行してください。
- 機能を変更したら、関連する機能テストも追加または更新し、`./vendor/bin/sail artisan test`で確認してください。実行できない場合は理由と未確認の範囲を共有してください。
- 変更したPHPは`./vendor/bin/sail pint`で整形してください。
- 実行済みのマイグレーションは編集・削除せず、スキーマ変更には新しいマイグレーションを追加してください。
- `.env`の中身は読まないでください。設定値を確認したいときは`.env.example`を見てください。`.env`を変更せず、認証情報をコミットしないでください。

## テスト方針

PHPUnit 10を使用します。ファイル名は`*Test.php`、メソッド名は検証内容が分かる`test_*`形式にしてください。HTTP、認可、DBを扱うシナリオは機能テスト、独立したロジックは単体テストに配置します。`docs/design/acceptance.md`を基に、入力チェックや権限不足のケースも検証してください。DBを扱うテストには、設定済みの`testing`データベースを使用します。カバレッジの数値目標は設定されていません。対象を絞る場合は`./vendor/bin/sail artisan test --filter=ExampleTest`を使います。

## コミット・プルリクエストの方針

直近の履歴では、`docs: add acceptance criteria and finalize review decisions`のように、接頭辞付きの簡潔な命令形の件名を使用しています。変更内容に合う接頭辞を付け、この形式に従ってください。イシュー1件につきブランチを1本作り、プルリクエスト1本で受け取ります。本文には動作の変更内容、検証コマンドとテスト結果、受け入れ条件それぞれの判定を記載し、最後に`Closes #<イシュー番号>`を入れてください。画面を変更した場合はスクリーンショットを添付し、関連する設計書の更新も含めてください。`.env`や認証情報はコミットしないでください。

PR本文は次の型を使い、各Issueの受け入れ条件に合わせて記入してください。

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
