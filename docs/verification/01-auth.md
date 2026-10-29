# Issue #1 実装・検証記録

検証実施：2026-10-29〜2026-10-30。記録更新：2026-10-30。ブランチ：`feat/1-authentication`。

## 実装

Fortify 1.36.2のコントローラー・認証パイプラインを使用し、画面は自前のBladeとした。`config/fortify.php`は登録だけを有効にし、必要な認証ルートだけを登録した。拡張は登録Action、ログイン用FormRequest、正規化ミドルウェア、Response契約とServiceProviderに配置した。

要件・画面・保存内容は転記せず、[features.md](../design/features.md)のF-01〜F-03・F-22、[screens.md](../design/screens.md)のSC-01・SC-02、[data.md](../design/data.md#認証の実装設計issue-1)、[permissions-api.md](../design/permissions-api.md#fortifyの適用範囲issue-1)、[behavior.md](../design/behavior.md#fortifyによる認証の実装issue-1)を参照する。チャンネル一覧の実装範囲は[screens.mdのユーザー決定](../design/screens.md#issue-1の実装範囲)を参照する。

## 自動検証

```bash
APP_ENV=example COMPOSE_ENV_FILES=.env.example ./vendor/bin/sail pint --dirty
APP_ENV=example COMPOSE_ENV_FILES=.env.example ./vendor/bin/sail artisan test --env=example
```

- Pint：成功。
- PHPUnit：59件成功、442アサーション、最終実行23.61秒。
- `git diff --check`：成功。
- テスト範囲は[acceptance.md](../design/acceptance.md#テスト観点)のTP-1・関連するTP-Cを参照。不要ルートの非公開、Fortifyコントローラーの利用、Q-39の期限後の復帰・キーの分離も確認した。
- 開発用`.env`は読み込まず、テストは`.env.example`とphpunit.xmlのserver設定を使った。DBはMySQLのtestingに固定した。

登録パスワード上限の変更後、TP-1-2の72文字成功・73文字エラー（日本語入力を含む）と、登録画面の案内文を再検証した。

レビュー指摘への対応後、配列で送った表示名・メールをOld Inputに残さずフォームを200で再表示できることと、未認証のGET /api/userへのJSON要求が転送せず401 JSONを返すことを機能テストで確認した。通常のGET /channelsへの未認証アクセスは302で/loginへ移動する。

CSRF不一致時の自動転送への変更後、トークン未送信・古いトークンのPOSTがログインへ302で戻り、登録・認証・ログアウトを実行しないことを機能テストで確認した。JSON要求は419を維持した。セッション側のトークン更新で古いフォームを再現しており、実時間を待つ期限切れや今回変更後のブラウザ操作は未実施。仕様は[behavior.mdの例外方針](../design/behavior.md#例外方針)を参照する。

## 画面確認

Playwright MCP、1280×900px、testing DB、確認用サーバー`http://localhost:5173`で実施。条件本文は[acceptance.md「1. 認証」](../design/acceptance.md#1-認証)を参照する。

| 条件 | 判定 | 観測した根拠・証拠 |
|:--|:--|:--|
| AC-1-1 | 満たす | 未登録メールで登録後、`/channels`に「認証確認 社員」が表示された。[画像](../../uat-shots/01_registration.png) |
| AC-1-2 | 満たす | 佐藤の固定アカウントでログインし、一覧上部に「佐藤 太郎」が表示された。[画像](../../uat-shots/02_login.png) |
| AC-1-3 | 満たす | 誤パスワードのエラーが表示され、パスワード欄は空。続けて一覧へ直接アクセスするとログイン画面へ戻った。[画像](../../uat-shots/03_wrong_password.png) |
| AC-1-4 | 満たす（操作上の制約あり） | 上部のフォームを送信してログアウト後、一覧への再アクセスがログイン画面へ移動した。[画像](../../uat-shots/04_logout_protection.png) |
| Q-39 | 満たす（操作上の制約あり） | 同じ未登録メールで5回は認証エラー、6回目はHTTP 429と再試行の案内になった。[画像](../../uat-shots/05_login_rate_limit.png) |

Playwrightの通常クリックが送信されない場面があり、AC-1-4とQ-39の最終確認では表示済みのボタンを対象にDOMの`click()`を併用した。通常のCSRF付きフォームを送信しており、サーバー認可を迂回するリクエストは送っていない。実機マウスの安定した操作は未確認。429時のコンソールのリソース取得エラーは、意図したHTTP 429応答によるもの。

2026-10-30の実装範囲の更新後、登録・ログインからの遷移を再確認し、画像01・02を差し替えた。上部バーと見出しの表示、および後続Issueへ回した要素が表示されないことを画面と機能テストで確認した。範囲の定義は[screens.md](../design/screens.md#issue-1の実装範囲)を参照する。

パスワード上限変更後の案内文は機能テストで確認した。上記の証拠画像は上限変更前のもので、今回の文言変更後の画面撮影は未実施。

## 依存関係の監査

`APP_ENV=example COMPOSE_ENV_FILES=.env.example ./vendor/bin/sail composer audit --format=json`は失敗（終了コード1）。既存6パッケージに11件のadvisoryが残る。composer.lockをHEADと比較し、該当パッケージのバージョンはすべて導入前と同じで、Fortifyおよび追加依存のadvisoryはなかった。

対象はlaravel/framework、league/commonmark、league/flysystem、symfony/http-foundation、symfony/mime、symfony/yaml。Laravel 10の既存依存全体の更新は今回実施していない。メール検証については[Laravelのアドバイザリー](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq)を確認し、内部CR・LFの拒否を登録・ログインの検証とテストに追加した。これにより依存監査全体が解消したとは扱わない。

## 後片付け・適用範囲

画面確認は`.env.example`から作った一時設定で起動した。確認用アカウントとSeederの3件はtesting DBから削除し、usersが0件であることを確認した。確認用サーバーを停止し、一時設定・Playwrightの中間スナップショットを削除した。5枚の証拠画像はuat-shots/へ保存した。

ユーザーの手動確認時には、開発DBの認証用カラムが存在することを確認し、固定3アカウントを一時投入した。確認後、ユーザー指定により固定3件とユーザーが検証で登録した1件を削除した。既存アカウントを持つ環境への適用についてはdata.mdの実装設計を参照する。機能実装に加え、テスト起動設定とPHP 8.5のPDO定数互換性を修正した。
