## 目的

設計書に従い、公開API機能を実装する。

## 対象・確定した要件

- 対象画面: 画面なし（JSON API）
- F-20（入力制約とエラー表示）・F-21（共通の画面・状態表現）は各機能へ適用する。

以下はfeatures.mdの対象行。リンク先はGitHubで参照できるURLに置き換えている。

| ID | 機能 | 対象・要件 | 根拠 | 関連する確認事項 |
|:--|:--|:--|:--|:--|
| F-18 | 公開API：チャンネル一覧 | 認証なしで公開チャンネルの一覧を読み取れる。プライベートの情報は名前も含めて一切返さない。10件ずつのページ移動で初回のID上限を引き継ぐ | [仕様 §3-7](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-7-公開api)（APIの画面見本はなし） | Q-12、Q-15、Q-31 |
| F-19 | 公開API：メッセージ一覧 | 認証なしで指定した公開チャンネルのメッセージ一覧を読み取れる。プライベートを指定しても情報を返さない。APIからの投稿・変更は提供しない。通常投稿5件・返信5件の別枠とし、共通の初回ID上限を引き継ぐ | [仕様 §3-7](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-7-公開api)（APIの画面見本はなし） | Q-12、Q-13、Q-15、Q-31 |

## 現状・実装時の整理

既存のroutes/api.phpには雛形の認証付きGET /api/userがある。設計の公開APIは認証不要の2種類に限定されているため、実装時に雛形ルートの扱いも設計と整合させる。

業務要件の未決事項はなし。Q-01〜Q-38の決定に従う。DBの具体的な制約・認証の保存方法などdata.md末尾の実装設計事項は、実装前に設計書へ具体化する。実装と設計が食い違う場合はdocs/design/を先に更新する。

## 受け入れ条件

出典: [acceptance.md「7. 公開API」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/acceptance.md)。以下の4行は原文をそのまま転記。

- [ ] **AC-7-1** 未ログインで `/api/channels` にアクセスすると、公開チャンネルだけが作成順で最大10件、`data`・`meta`・`links`を持つJSONになる。
- [ ] **AC-7-2** 未ログインで `/api/channels/{channel}/messages` に公開チャンネルのIDでアクセスすると、未削除の通常投稿と返信が新しい順で最大5件ずつ、別の配列に入ったJSONになる。
- [ ] **AC-7-3** 未ログインでチャンネル一覧を経由せずプライベートのメッセージAPIに直接アクセスすると、名前や本文を含まない `NOT_FOUND` のエラーJSONになる。
- [ ] **AC-7-4** 「採用プロジェクト」の作成者である佐藤がログインした状態で `/api/channels` にアクセスすると、自分が作ったプライベートも含まれず、公開チャンネルだけのJSONになる。

## 確認の前提

PCブラウザで操作し、各行を個別に確認する。初期データの「採用プロジェクト」を基準に、3人を次の役として固定する。初期の作成者・所属は[機能一覧のF-22](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md)に従う。この役分担は確認対象のチャンネル内での役であり、公開チャンネルでは3人とも閲覧・投稿できる。

| 利用者 | 固定する役 | 主に確認すること |
|:--|:--|:--|
| 佐藤 太郎 | チャンネル作成者 | チャンネル編集・削除、メンバー追加・解除 |
| 鈴木 花子 | 参加中の一般メンバー | 閲覧・投稿・返信、自分の投稿の編集・削除、管理操作の制限 |
| 高橋 健 | 未参加の社員 | プライベートの存在非開示、直接URLでの拒否、追加後の利用 |

- メンバー追加・解除は高橋を対象とし、各項目は初期の所属から開始する。解除の確認では、事前に高橋を追加しておく。
- メッセージの本人操作は鈴木の投稿・返信で確認し、他人による操作の制限は佐藤で確認する。編集競合には鈴木でログインした2つのタブを使う。
- 別の利用者での確認には別のブラウザセッションを使う。未ログインの確認には、3人ともログインしていない別のブラウザセッションを使う。公開APIは各条件で指定したログイン状態でURLを開き、JSONを確認する。
- 新規登録の確認には、未登録のメールアドレスを使う。
- チャンネル削除は最後に行うか、項目ごとに佐藤が作成した確認用チャンネルを用意する。確認用プライベートも鈴木は参加、高橋は未参加から開始する。
- 対象の権限・投稿・返信・ページ数に必要なデータは確認前に用意する。前の項目で編集・削除した投稿を次の項目の前提に使う場合は、必要な状態に整えてから始める。

## テスト観点

acceptance.mdの該当機能と共通の観点を使用する。受け入れ条件とは分けて検証する。

| ID | 何を確かめるか | 区分 | 元になった設計書 |
|:--|:--|:--|:--|
| TP-7-1 | 認証なしの2つのGET、作成者がログインして呼び出しても公開だけの返却範囲、JSONのContent-Type、チャンネルの作成順、投稿・返信の新しい順と同時刻のID順 | 正常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 公開APIの共通ルール・各エンドポイント、[features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-18・F-19 |
| TP-7-2 | `data`・`meta`・`links`、各返却項目・型・null・JSTのISO 8601日時が契約に一致し、メール・個別削除済みの元本文・内部の版番号等を混入させないこと | 正常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 出力・JSON例、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-12 |
| TP-7-3 | チャンネル10件／超過、通常投稿と返信は各5件／超過の独立枠で、一方の不足を他方で埋めず、同じページ番号を使うこと | 境界値 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) ページ分割、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-15 |
| TP-7-4 | 個別削除済みを集計前に除外した各総数、2枠の大きい方で決まる最終ページ、同じ上限を保つ前後リンク | 正常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) `meta`・`links`・ページ分割、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) 取得条件 |
| TP-7-5 | 空一覧・片方だけ空・範囲外ページを200と所定の空配列で返し、最終ページは最低1とすること | 境界値 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 空・ページ範囲外の例、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 0件の扱い |
| TP-7-6 | 別ページ・個別削除済みの親でも未削除返信に親IDを返し、親の本文は漏らさず、返信件数は削除済み・初回上限より新しい返信も含む現在の全件数になること | 正常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) メッセージ出力・返信件数、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-06・Q-13・Q-31 |
| TP-7-7 | 初回上限は返せる公開対象だけから求め、メッセージ・返信は同じ上限を使い、ページ移動で維持し、上限なしで開き直すと取り直すこと | 正常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 初回のID上限、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-31 |
| TP-7-8 | 上限0と正のページ番号、負数・空・小数・配列・数値以外などの不正値を区別し、不正クエリは422の項目別エラーになること | 境界値 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 入力・422のJSON例 |
| TP-7-9 | プライベート・削除済み・不存在・不正形式のチャンネルIDを同じ404にし、クエリ検証より先に対象の公開可否を判定して名前・存在・本文を返さないこと | 異常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 共通ルール・404、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針 |
| TP-7-10 | HEADはGET相当のステータス・ヘッダーで本文なし、変更メソッド等は405と `Allow: GET, HEAD` になり、APIから変更できないこと | 異常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) HTTPメソッド・405、[features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) 今回作らないもの |
| TP-7-11 | 404・405・422・500を `error.code`・`message`・`details` のJSONで返し、成功用フィールド・HTML・ログインへのリダイレクト・内部例外を混ぜないこと | 異常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) エラー形式・各JSON例、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針 |
| TP-C-1 | PC幅1,280pxでの共通配置・見本の状態表現・日本時間表示、静的な上限案内、JavaScriptなしの画面再表示と手動更新 | 正常系 | [features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-20・F-21、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) 共通表示、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-16・Q-18 |
| TP-C-2 | 初期3アカウントと4チャンネルの作成者・所属、公開ごとの投稿量・著者と日時の分散、返信付き・編集済み・削除済み・プライベートの初期会話 | 正常系 | [features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-22・F-23、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) 初期データ |
| TP-C-3 | GETで変更せず、フォームのメソッド・CSRF・サーバー側の投稿者／作成者確定・対象IDの整合・安全な内部戻り先を検証すること | 異常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 共通ルール・ルート・戻り先、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針 |
| TP-C-4 | 入力エラーの項目表示・文言・非機密の再入力値保持、変更フォームの303と不正GETの422、パスワード・確認用パスワードを入力エラー後に引き継がず、保存はハッシュだけにすること | 異常系 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) 入力チェック、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) users |
| TP-C-5 | 変更時の権限再確認とトランザクション、チャンネル削除・所属解除・投稿・編集・削除の競合順序、失敗時に一部だけ保存しないこと | 異常系 | [behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 保存・同時操作・失敗、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) 制約・CRUD表 |
| TP-C-6 | DB障害は500で内部情報を伏せ、ログにも本文・パスワードを残さず、確定後の応答失敗では保存を巻き戻さず自動再送しないこと | 異常系 | [behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針・保存後の失敗、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) エラー |

## 実装・検証の共通方針

- Laravel 10の標準MVC構成を使い、画面はJavaScriptを使わずリンクとフォーム送信で操作する。
- 権限、対象取得と拒否の順序、削除状態と用途別の取得条件、トランザクション・同時操作、入力・エラー・戻り先は関連設計を守る。
- 機能テストを追加・更新し、`./vendor/bin/sail artisan test`で確認する。DBはtestingを使う。
- 変更したPHPは`./vendor/bin/sail pint`で整形する。実行できない確認は理由と範囲をPRに記載する。
- IssueごとにブランチとPRを分け、PR本文はAGENTS.mdの型に従い、各受け入れ条件の判定を記載する。画面を変更した場合はスクリーンショットを添付する。

## 参照資料

設計書一式を参照する。個別の要件と決定・契約は該当するF・SC・Q・AC・TP番号からたどる。

- [](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/README.md)
- [](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md)
- [](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md)
- [](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md)
- [](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md)
- [](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md)
- [](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md)
- [](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/acceptance.md)
- [仕様書](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md)
- [画面カタログ](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/index.html)
- [デザイン指定書](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/design-guide.md)
- [リポジトリの決めごと](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/AGENTS.md)

## 対象外

features.md「今回作らないもの」に従う。設計にない画面・API・復元・編集履歴を追加しない。

## 関連Issue・進め方

- https://github.com/Tomoyuki-Kuge/teamchat-app/issues/2（チャンネル）
- https://github.com/Tomoyuki-Kuge/teamchat-app/issues/4（メッセージ）
- https://github.com/Tomoyuki-Kuge/teamchat-app/issues/5（スレッド）

起票順は認証→チャンネル→メンバー管理→メッセージ→スレッド→検索→公開API。前段で作成したデータ・認可・共通処理を利用し、機能をまたぐ受け入れ条件は関連機能が揃った時点で結合確認する。
