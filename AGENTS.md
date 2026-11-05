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

## 作業時の共通ルール

- Laravelコマンドは`./vendor/bin/sail artisan`経由で実行してください。初回セットアップは`README.md`を参照してください。
- 書式は`.editorconfig`に従い、変更したPHPは`./vendor/bin/sail pint`で整形してください。
- 画面操作はJavaScriptを使わず、リンクとフォーム送信で実装します。具体的な契約は`docs/design/screens.md`「操作・表示の共通方針」を参照してください。
- 設計書の書き方は`docs/design/README.md`を参照してください。
- `.env`は閲覧・変更しないでください。設定値は`.env.example`で確認し、認証情報をコミットしないでください。

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

### 変更対象ごとの実装ルール

実装前に、変更対象に対応する以下のルールも読んでください。複数に該当する場合はすべて確認します。対象パターンはリポジトリルートからの相対パスで、Markdownの自動読み込みを指定する設定ではありません。この表を使って必要なファイルを明示的に読みます。

| 変更対象 | 読むルール |
| --- | --- |
| `app/**/*.php`、`routes/**/*.php` | [Laravelの責務と構成](.codex/rules/laravel-architecture.md) |
| `app/Http/**/*.php`、`routes/**/*.php`、`app/Support/Messages/**/*.php`、`app/Exceptions/**/*.php` | [HTTP・入力・フォーム](.codex/rules/http.md) |
| `app/Policies/**/*.php`、`app/Http/**/*.php`、`app/Actions/**/*.php`、`app/Support/Messages/**/*.php`、`app/Providers/AuthServiceProvider.php` | [認可と対象確認](.codex/rules/authorization.md) |
| `app/Models/**/*.php`、`app/Actions/**/*.php`、`app/Support/Messages/**/*.php`、`database/**/*.php` | [保存と取得](.codex/rules/persistence.md) |
| `app/Providers/**/*.php`、`app/Http/Middleware/**/*.php`、`app/Http/Responses/**/*.php`、`app/Http/Kernel.php`、`app/Exceptions/**/*.php`、`app/Actions/Fortify/**/*.php`、`app/Support/Auth*.php`、`app/Http/Requests/LoginRequest.php`、`app/Models/User.php`、`config/fortify.php`、`routes/**/*.php` | [フレームワークとの連携](.codex/rules/framework-integration.md) |
| `tests/**/*.php`、および機能の振る舞いを変更する作業全般 | [テスト](.codex/rules/testing.md) |

各ルールは実装時の判断基準を扱います。文字数・件数・文言・判定順序などの具体的な契約は、上の表から関連する設計書を確認してください。ルールと設計が食い違った場合は独断で実装を変えず、不一致を整理してください。

`.codex/rules/*.md`はこの案内から読む実装ルールです。`sail-tests.rules`などの`.rules`ファイルはコマンド実行の許可を扱います。

## 実装担当サブエージェント

承認済み計画に基づく機能実装と自動テストは、[implementer](.codex/agents/implementer.toml)を1人起動して委任してください。局所的な誤字・文書・設定の修正は、親が直接行って構いません。親の分担・引き継ぎ・差分レビューの手順は[start-prep「承認後、実装担当へ引き継ぐ」](.codex/skills/start-prep/SKILL.md#7-承認後実装担当へ引き継ぐ)に従ってください。

## ブランチとプルリクエストの方針

PRの作成・更新の手順は[create-pr Skill](.codex/skills/create-pr/SKILL.md)を参照してください。命名規約とPR本文の型は、この節を正本とします。

直近の履歴では、`docs: add acceptance criteria and finalize review decisions`のように、接頭辞付きの簡潔な命令形のコミット件名を使用しています。変更内容に合う接頭辞を付け、この形式に従ってください。

イシュー1件につきブランチを1本作り、プルリクエスト1本で受け取ります。イシューに紐づかない修正も、変更の目的ごとにブランチとプルリクエストを分けてください。ブランチ名は次の形式にします。

| 対象 | ブランチ名の形式 | 例 |
| --- | --- | --- |
| イシューに紐づく変更 | `<種別>/<イシュー番号>-<概要>` | `feat/1-authentication` |
| イシューに紐づかない変更 | `<種別>/<概要>` | 物差し・開発設定の修正は `chore/harness-gaps`、設計書だけの修正は `docs/auth-design` |

種別は機能追加なら`feat`、不具合修正なら`fix`、文書だけなら`docs`、開発環境や`.claude/`・`.codex/`の物差しの整備なら`chore`を使います。概要は英小文字・数字・ハイフンで、変更内容が分かる短い名前にしてください。

イシューに紐づく場合は最後に`Closes #<イシュー番号>`を入れてください。紐づかない場合はIssue欄を「該当なし」とし、`Closes`の行は省略します。画面を変更した場合はスクリーンショットを添付してください。

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
