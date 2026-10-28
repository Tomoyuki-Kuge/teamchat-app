## 目的

設計書に従い、メンバー管理機能を実装する。

## 対象・確定した要件

- 対象画面: SC-09
- F-20（入力制約とエラー表示）・F-21（共通の画面・状態表現）は各機能へ適用する。

以下はfeatures.mdの対象行。リンク先はGitHubで参照できるURLに置き換えている。

| ID | 機能 | 対象・要件 | 根拠 | 関連する確認事項 |
|:--|:--|:--|:--|:--|
| F-09 | プライベートのメンバー一覧・追加対象の検索 | プライベートチャンネルの作成者だけが管理画面を開く。人数・表示名・メールアドレス・作成者の印を表示し、表示名またはメールアドレスで追加対象を探せる。比較用の検索語・対象テキストだけをNFKC正規化・英字小文字化し、入力語全体で部分一致検索する。候補は表示名→ID順で既存メンバーを除く。空検索は候補を出さず、検索0件は正常な案内とする | [仕様 §3-3](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-3-メンバー管理)、[メンバー管理](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/members.html)、通しレビュー後のユーザー決定 | Q-07、Q-29、Q-35 |
| F-10 | プライベートのメンバー追加・解除 | 作成者だけがメンバーを追加・解除する。追加された人はその時点から閲覧・投稿できる。作成者は外せず、無効の「外す」と「作成者は外せません」を表示する。公開チャンネルに管理操作は設けない | [仕様 §3-2・§3-3](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-3-メンバー管理)、[メンバー管理](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/members.html) | — |

## 現状・実装時の整理

所属の追加・解除はchannel_userを使用する。解除直後の閲覧・投稿・検索制御は後続機能でも現在の所属を再確認する。

業務要件の未決事項はなし。Q-01〜Q-38の決定に従う。DBの具体的な制約・認証の保存方法などdata.md末尾の実装設計事項は、実装前に設計書へ具体化する。実装と設計が食い違う場合はdocs/design/を先に更新する。

## 受け入れ条件

出典: [acceptance.md「3. メンバー管理」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/acceptance.md)。以下の4行は原文をそのまま転記。

- [ ] **AC-3-1** プライベートチャンネルの作成者が未参加社員の表示名またはメールアドレスの一部で検索すると、その社員が追加候補になる。
- [ ] **AC-3-2** 作成者が候補を選んで追加し、追加された社員でチャンネルを開く操作をすると、その社員も閲覧・投稿できる状態になる。
- [ ] **AC-3-3** 作成者がメンバーを外し、外された社員で同じチャンネルのURLに再アクセスすると、チャンネルの内容が表示されない状態になる。
- [ ] **AC-3-4** 作成者がメンバー一覧を表示すると、自分の「外す」は無効で「作成者は外せません」と表示され、解除できない状態になる。

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
| TP-3-1 | プライベートの作成者だけが管理でき、公開チャンネルには管理操作がなく、他人の管理要求が拒否されること | 異常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) F-09・F-10・メンバールート、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-09 |
| TP-3-2 | 人数・表示名・メール・作成者表示、検索用のNFKC・英字小文字化後の入力語全体の表示名またはメールへの部分一致、複数語ANDを使わないこと、表示名→ID順、既存メンバーの候補除外 | 正常系 | [features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-09、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-29・Q-35、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-09 |
| TP-3-3 | 空・空白だけの検索では候補を出さず、一致0件は正常な案内になること | 境界値 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-29、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-09 |
| TP-3-4 | 選択した社員の追加と即時の閲覧・投稿許可、追加後のメンバー表示と候補からの除外 | 正常系 | [features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-10、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) メンバールート |
| TP-3-5 | 解除直後に通常画面・スレッド・検索・投稿の権限を失い、本人のアカウントと過去の投稿・返信は保持されること | 正常系 | [data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) channel_user・CRUD表、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 権限ルール、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 権限再確認 |
| TP-3-6 | 作成者解除の無効表示とサーバーでの403、未選択・未登録社員の追加時の入力エラー | 異常系 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-09・入力チェック、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) メンバールート |
| TP-3-7 | 既存メンバーの再追加・解除済みメンバーの再解除は変更なしで成功し、所属の重複を作らないこと | 境界値 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) メンバールート、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) channel_user |
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

- https://github.com/Tomoyuki-Kuge/teamchat-app/issues/1（認証）
- https://github.com/Tomoyuki-Kuge/teamchat-app/issues/2（チャンネル）

起票順は認証→チャンネル→メンバー管理→メッセージ→スレッド→検索→公開API。前段で作成したデータ・認可・共通処理を利用し、機能をまたぐ受け入れ条件は関連機能が揃った時点で結合確認する。
