## 目的

設計書に従い、スレッド機能を実装する。

## 対象・確定した要件

- 対象画面: SC-06
- F-20（入力制約とエラー表示）・F-21（共通の画面・状態表現）は各機能へ適用する。

以下はfeatures.mdの対象行。リンク先はGitHubで参照できるURLに置き換えている。

| ID | 機能 | 対象・要件 | 根拠 | 関連する確認事項 |
|:--|:--|:--|:--|:--|
| F-15 | スレッド閲覧・返信件数 | 親メッセージに「返信N件」を表示する。返信操作・返信件数からチャンネル右側のパネルを開き、親メッセージ・返信件数・返信を表示し、「閉じる」で閉じる。削除済みの親でも既存の返信を読める | [仕様 §3-5](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-5-スレッド)、[スレッド](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/thread.html)、[ガイド §4「返信あり」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/design-guide.md#4-状態の決まり) | Q-05、Q-06、Q-15 |
| F-16 | スレッド返信 | 投稿権限のあるチャンネルのスレッド内で返信を入力・送信できる。見本では削除済みの親にも返信欄を設けている。通常投稿と返信の入力欄を分け、返信本文も1,000文字までとする。入力エラー時もスレッドと表示範囲を保ち、返信欄に本文とエラーを戻す | [仕様 §3-5・§5-1](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md)、[削除済みの親と返信欄](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/thread.html)、通しレビュー後のユーザー決定 | Q-05、Q-34 |

## 現状・実装時の整理

メッセージ機能のmessages・本人の編集／削除処理を共用する。F-23の返信付き親2件以上（うち1件は返信3件以上）を用意する。返信への返信は追加しない。

業務要件の未決事項はなし。Q-01〜Q-38の決定に従う。DBの具体的な制約・認証の保存方法などdata.md末尾の実装設計事項は、実装前に設計書へ具体化する。実装と設計が食い違う場合はdocs/design/を先に更新する。

## 受け入れ条件

出典: [acceptance.md「5. スレッド」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/acceptance.md)。以下の4行は原文をそのまま転記。

- [ ] **AC-5-1** 親メッセージの返信件数からスレッドを開く操作をすると、チャンネルの右側に親・全返信件数・返信一覧が表示される状態になる。
- [ ] **AC-5-2** スレッド内で本文を入力して返信すると、通常投稿には混ざらず、そのスレッドの返信一覧と全返信件数に反映された状態になる。
- [ ] **AC-5-3** 返信のある親を本人が個別削除してスレッドを開き直す操作をすると、親は削除表示になり、既存の返信を読んで新たに返信できる状態になる。
- [ ] **AC-5-4** スレッドで1,001文字の本文を送信すると、「メッセージは1,000文字以内で入力してください」と表示され、スレッドを開いた返信欄に本文が残り、未投稿の状態になる。

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
| TP-5-1 | 返信操作・件数からの開閉、右パネルの親・全返信件数・返信一覧、通常投稿とは別の入力欄と投稿先 | 正常系 | [features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-15・F-16、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-06 |
| TP-5-2 | 返信0件・10件・11件、初回最新10件と以前の返信追加、親・全返信件数・表示済み範囲・位置の維持 | 境界値 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-15、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-06、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 返信表示範囲 |
| TP-5-3 | 親の個別削除後も既存返信の閲覧・追加ができ、削除済み返信は元の位置に残り、全返信件数にも含まれること | 正常系 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-05・Q-06、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 代表シーケンス・状態と取得 |
| TP-5-4 | 返信の投稿・編集・削除にも通常投稿と同じ本文制約・本人制限・競合確認・削除表示を適用すること | 正常系 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-06、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) メッセージルート、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) メッセージの状態 |
| TP-5-5 | 同じチャンネルの通常投稿だけを親にでき、別チャンネル・返信を親にする要求や不正な親子関係を拒否すること | 異常系 | [data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) messages、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) スレッドルート、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針 |
| TP-5-6 | 親削除時は返信の本文・状態を変えず、チャンネル削除時は親・全返信の状態にかかわらず閲覧・追加・編集・削除を終了すること | 正常系 | [behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 状態遷移・代表シーケンス、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) 保持方針、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-26 |
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

起票順は認証→チャンネル→メンバー管理→メッセージ→スレッド→検索→公開API。前段で作成したデータ・認可・共通処理を利用し、機能をまたぐ受け入れ条件は関連機能が揃った時点で結合確認する。
