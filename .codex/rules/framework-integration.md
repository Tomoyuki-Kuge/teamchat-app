# フレームワークとの連携のルール

## 対象

- `app/Providers/**/*.php`
- `app/Http/Middleware/**/*.php`
- `app/Http/Responses/**/*.php`
- `app/Http/Kernel.php`
- `app/Exceptions/**/*.php`
- `app/Actions/Fortify/**/*.php`
- `app/Support/Auth*.php`
- `app/Http/Requests/LoginRequest.php`
- `app/Models/User.php`
- `config/fortify.php`
- `routes/**/*.php`

## 実装時の判断

- 認証はFortifyの契約・拡張ポイントを使う。独自の認証処理を並立させず、既存のRequest・Action・Responseの差し替えを確認する。
- Fortifyの自動ルートを無効にしている既存方針を確認し、パッケージ設定やルートの変更で設計外の機能を公開しない。
- Providerでは`register`のサービス登録と`boot`の起動設定を区別し、個別操作の保存処理はActionへ置く。
- Middlewareの順序を変える場合は、CSRF・認証・入力正規化・回数制限・Bindingへの影響を確認する。Laravel 10の既存Kernelとルート設定に合わせる。
- 入力の正規化は項目別の設計に従う。一律trim・一律小文字化を追加せず、既存の`AuthInput`・`AuthValidation`・`TrimStrings`の役割を確認する。
- ユーザーのパスワード列・ハッシュ化・認証時の取得は、既存のUserモデルとFortify登録Actionの対応を保つ。
- パスワード・確認用パスワードをOld Inputへ残さない。障害ログには操作・対象ID・例外識別に必要な情報を記録し、本文やパスワードを含めない。エラー応答へSQLやスタックトレースを出さない。
- 想定内の入力・認可・状態エラーと、予期しない障害を区別する。広いcatchで成功扱いにしたり、障害を通常の入力エラーへ変換したりしない。
- 既存のHandlerによる例外報告・応答の拡張を確認し、画面とJSONの応答契約を保つ。
- コミット後の応答失敗では保存を取り消せない。自動再送やロールバック済みという案内を行わず、設計に沿った結果確認を案内する。

## 設計の参照先

- [behavior.md「Fortifyによる認証の実装（Issue #1）」](../../docs/design/behavior.md#fortifyによる認証の実装issue-1)
- [behavior.md「例外方針」](../../docs/design/behavior.md#例外方針)
- [behavior.md「更新の整合性と同時操作」](../../docs/design/behavior.md#更新の整合性と同時操作)
- [data.md「認証の実装設計（Issue #1）」](../../docs/design/data.md#認証の実装設計issue-1)
- [screens.md「入力チェックとエラーメッセージ」](../../docs/design/screens.md#入力チェックとエラーメッセージ)
