## 目的

設計書に従い、チャンネル機能を実装する。

## 対象・確定した要件

- 対象画面: SC-03・SC-04・SC-05（チャンネル表示）・SC-08
- F-20（入力制約とエラー表示）・F-21（共通の画面・状態表現）は各機能へ適用する。

以下はfeatures.mdの対象行。リンク先はGitHubで参照できるURLに置き換えている。

| ID | 機能 | 対象・要件 | 根拠 | 関連する確認事項 |
|:--|:--|:--|:--|:--|
| F-04 | チャンネル一覧・切り替え | ログインした社員が一覧と左サイドバーからチャンネルを開く。一覧に名前・説明・公開／プライベート・作成者を表示する。公開は `#`、プライベートは鍵で区別し、作成者に編集、プライベートの作成者にメンバー管理の導線を表示する | [仕様 §3-2](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-2-チャンネル)、[チャンネル一覧](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/channels.html) | Q-03、Q-14 |
| F-05 | チャンネル作成 | ログインした社員が名前・説明・公開範囲を指定して作成する。名前は必須、説明は任意。作成者を自動でメンバーにする。「キャンセル」で一覧へ戻る | [仕様 §3-2](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-2-チャンネル)、[作成画面](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/channel-new.html) | Q-04、Q-27、Q-28、Q-32 |
| F-06 | チャンネルの閲覧・投稿制御 | 公開チャンネルはログインした社員全員、プライベートは追加されたメンバーだけが閲覧・投稿できる。スレッドと検索も、その人が見られるチャンネルの範囲に限る。未ログイン利用は公開APIによる読み取りだけとする | [仕様 §2・§3-2・§3-3・§3-6](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md)、[検索結果の注記](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/search.html) | Q-03 |
| F-07 | チャンネル編集 | 作成者だけが名前・説明を変更する。公開範囲は作成後に変更できない。「保存する」と「キャンセル」を設ける | [仕様 §3-2](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-2-チャンネル)、[編集画面](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/channel-edit.html) | Q-04、Q-27、Q-28、Q-32 |
| F-08 | チャンネル削除 | 作成者だけが削除でき、配下の全メッセージ・全返信を未編集・編集済み・個別削除済みの区別なくまとめて終了させる。本文・削除表示・返信件数を含めて画面・検索・公開APIから除外する。チャンネル・所属情報・会話の行と本文は保持し、チャンネルに削除日時を持つ。削除前に対象名、メッセージと返信の件数、取り消せないことを表示し、チャンネル名を入力して確認する。「やめる」で中止できる | [仕様 §3-2](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-2-チャンネル)、[削除確認](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/channel-edit.html)、[ガイド §4「削除の確認」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/design-guide.md#4-状態の決まり)、[データ保持のユーザー決定](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) | Q-21・Q-26 |

## 現状・実装時の整理

channels・channel_userは未作成。作成者の所属登録とチャンネル作成を同一トランザクションで実装する。F-22の固定4チャンネル・所属を用意する。配下の会話表示・投稿・検索・公開APIとの結合確認は後続Issueと合わせて行う。

業務要件の未決事項はなし。Q-01〜Q-38の決定に従う。DBの具体的な制約・認証の保存方法などdata.md末尾の実装設計事項は、実装前に設計書へ具体化する。実装と設計が食い違う場合はdocs/design/を先に更新する。

## 初期データ（この機能の担当）

- F-22：全体連絡・開発・雑談・採用プロジェクトの4チャンネルを用意する。
- 公開範囲・作成者・メンバーの割り当ては仕様書 §5-4の指定に従う。
- 認証機能で用意した3アカウントを利用する。

## 受け入れ条件

出典: [acceptance.md「2. チャンネル」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/acceptance.md)。以下の4行は原文をそのまま転記。

- [ ] **AC-2-1** 名前・説明・公開範囲を指定してチャンネルを作成すると、作成したチャンネルが開き、作成者自身が閲覧・投稿できる状態になる。
- [ ] **AC-2-2** 作成者が名前と説明を編集して保存すると、公開範囲を保ったまま変更後の表示になる。
- [ ] **AC-2-3** チャンネル名を空のまま作成を送信すると、「チャンネル名を入力してください」と表示され、作成されない状態になる。
- [ ] **AC-2-4** 作成者が削除確認で対象名を正しく入力して削除すると、チャンネル一覧に戻り、そのチャンネルのURLを開いても会話が表示されない状態になる。

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
| TP-2-1 | 一覧・サイドバーの作成順、名前・説明・種別・作成者、公開の `#` とプライベートの鍵、切り替えと空状態 | 正常系 | [features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-04、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-03・共通表示 |
| TP-2-2 | 作成時の公開範囲と作成者の自動参加、作成成功時の遷移、作成・編集のキャンセル | 正常系 | [features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-05・F-07、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-04・SC-08、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) channels・channel_user |
| TP-2-3 | 公開はログイン社員全員が利用でき、未参加プライベートは一覧・サイドバーに出ず、直接URLも存在しない対象と同じ404になること | 異常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) F-04〜F-06・権限ルール、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針 |
| TP-2-4 | 作成者だけが編集・削除できること、公開範囲は変更できないこと、表示中の他人のチャンネルの変更要求は403になること | 異常系 | [features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-07・F-08、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 権限・チャンネルルート |
| TP-2-5 | 名前の空・空白のみ・50文字／超過、説明の未入力・200文字／超過、公開範囲の必須・選択肢制約 | 境界値 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) 入力チェック、[features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-20 |
| TP-2-6 | 名前は原文で比較し、前後空白・英字大小・全角半角・Unicode差を別名として扱い、編集時は自分自身を重複対象から外すこと | 境界値 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-32、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) channels、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) 入力チェック |
| TP-2-7 | 公開・プライベートをまたぐ同名登録と同時作成の衝突が「使用できない名前です」になり、既存チャンネルの情報を返さないこと | 異常系 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-28、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) 入力チェック、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針 |
| TP-2-8 | 削除確認の対象名・通常投稿数・返信数・取り消せない旨、個別削除済みを含む実件数、確認用の名前の完全一致、キャンセルで変更しないこと | 境界値 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-08、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-21・Q-32、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) チャンネルルート |
| TP-2-9 | チャンネル削除が未編集・編集済み・個別削除済みの全投稿・全返信を終了させ、削除表示・返信件数も画面・検索・APIに残さず、以後の閲覧・変更を404にすること | 正常系 | [behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) メッセージの状態・例外方針、[features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-08、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-26 |
| TP-2-10 | 削除時にチャンネル・所属・会話の行と本文、個別の編集・削除の値を保持し、同じ名前を新しいチャンネルIDで再利用できること | 正常系 | [data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) 保持方針・channels、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-26・Q-27 |
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

起票順は認証→チャンネル→メンバー管理→メッセージ→スレッド→検索→公開API。前段で作成したデータ・認可・共通処理を利用し、機能をまたぐ受け入れ条件は関連機能が揃った時点で結合確認する。
