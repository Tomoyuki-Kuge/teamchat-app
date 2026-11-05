# Laravelの責務と構成

## 対象

- `app/**/*.php`
- `routes/**/*.php`

## 実装時の判断

- Laravel 10の既存構成と導入済みパッケージに合わせる。別バージョンの構成をそのまま持ち込まず、使用する機能との互換性を確認する。
- ControllerはHTTPの入口、処理の呼び出し、応答・画面遷移を扱う。複数行の保存やロックを伴う処理は保存用Actionへ置く。
- FormRequestは入力検証と、検証前に必要な対象確認・認可を扱う。拒否する順序を保つための対象確認も責務に含める。
- Policyは閲覧・操作の可否を判定し、保存やリダイレクトを行わない。
- Modelは関連、cast、用途が明確なscope、表示用アクセサを扱う。HTTPの応答やフォーム復元を持たせない。
- `Actions`は本プロジェクトの保存処理の配置先とする。認証登録は既存のFortifyの契約に従う。
- `Support`には目的が明確な共通処理を置く。会話の表示範囲・内部URL生成・再表示は既存の`ConversationRange`・`ConversationPage`を確認し、Controllerごとに複製しない。
- Providerはサービス登録と起動設定を扱い、個別操作の保存処理はActionへ置く。
- 新しいService・Repositoryなどの層は、既存の責務では収まらない理由がある場合に検討する。配置を揃えることだけを目的に既存コードを一括移動しない。
- ルートは入口とMiddlewareの対応を示し、処理本体を抱え込まない。依存オブジェクトは既存のコンテナ登録・メソッド注入を確認して利用する。

## 設計の参照先

- [behavior.md「構成」](../../docs/design/behavior.md#構成)
- [behavior.md「Fortifyによる認証の実装（Issue #1）」](../../docs/design/behavior.md#fortifyによる認証の実装issue-1)
- [permissions-api.md](../../docs/design/permissions-api.md)：ルーティングと公開APIの契約
