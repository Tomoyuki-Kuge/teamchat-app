## 目的

設計書に従い、メッセージ機能を実装する。

## 対象・確定した要件

- 対象画面: SC-05・SC-10
- F-20（入力制約とエラー表示）・F-21（共通の画面・状態表現）は各機能へ適用する。

以下はfeatures.mdの対象行。リンク先はGitHubで参照できるURLに置き換えている。

| ID | 機能 | 対象・要件 | 根拠 | 関連する確認事項 |
|:--|:--|:--|:--|:--|
| F-11 | メッセージ閲覧 | 閲覧権限のあるチャンネルの通常投稿を、投稿者・日時・本文とともに表示する。見本に合わせて古い投稿から並べ、日付の区切りを置く。返信は通常投稿の列に混ぜない | [仕様 §3-4・§3-5](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-4-メッセージ)、[チャンネル画面](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/channel-show.html) | Q-15、Q-16 |
| F-12 | メッセージ投稿 | 投稿権限のあるチャンネル内の入力欄から、本文を入力して「送信」できる。本文は1,000文字まで。文字数または上限の目安を表示する。入力エラー時は元の表示範囲と開いているスレッドを保ち、送信した欄に本文とエラーを戻す | [仕様 §3-4・§5-1](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md)、[チャンネル画面](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/channel-show.html)、[ガイド §4「文字数」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/design-guide.md#4-状態の決まり)、通しレビュー後のユーザー決定 | Q-34 |
| F-13 | 自分のメッセージ編集 | 自分のメッセージだけ編集できる。本文の位置を入力欄に変え、「保存」「やめる」を表示する。周囲の会話は残し、保存後の本文末尾に「編集済み」を表示する。同時編集では現在の本文と自分の入力を保存前に確認でき、確認後に成功した編集を採用する | [仕様 §3-4](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-4-メッセージ)、[チャンネル画面](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/channel-show.html)、[ガイド §4「編集中」「編集済み」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/design-guide.md#4-状態の決まり) | Q-30 |
| F-14 | 自分のメッセージ削除 | 自分のメッセージだけ削除できる。行・元本文を残して削除日時を持ち、元の位置には「このメッセージは削除されました」を表示する。元本文は画面・検索・公開APIへ出さない。投稿者・日時・既存の返信を残し、削除済み返信も位置を残して返信件数に含める | [仕様 §3-4](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-4-メッセージ)、[チャンネル画面](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/channel-show.html)、[ガイド §4「削除されました」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/design-guide.md#4-状態の決まり)、[データ保持のユーザー決定](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) | Q-06・Q-17・Q-24・Q-25 |

## 現状・実装時の整理

messagesは未作成。通常投稿と返信で同じテーブルを使用する。F-23の通常投稿・編集済み・削除済み・プライベートの初期会話を用意し、スレッド機能と共有する編集・削除・競合処理を整える。

業務要件の未決事項はなし。Q-01〜Q-38の決定に従う。DBの具体的な制約・認証の保存方法などdata.md末尾の実装設計事項は、実装前に設計書へ具体化する。実装と設計が食い違う場合はdocs/design/を先に更新する。

## 初期データ（この機能の担当）

- F-23：公開チャンネルごとに10件前後の自然な日本語の通常投稿を用意する。
- 投稿者・日時を分散し、プライベートチャンネルにも数件を用意する。
- 編集済みと削除済みのメッセージを各1件以上含める。

## 受け入れ条件

出典: [acceptance.md「4. メッセージ」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/acceptance.md)。以下の4行は原文をそのまま転記。

- [ ] **AC-4-1** 閲覧・投稿できるチャンネルで本文を入力して送信すると、自分の名前・投稿日時・本文が通常投稿の一覧に表示される状態になる。
- [ ] **AC-4-2** 自分のメッセージを編集して保存すると、その位置に変更後の本文と「編集済み」が表示される状態になる。
- [ ] **AC-4-3** 佐藤が鈴木の投稿の削除確認URLにアクセスすると、「この操作はできません」と表示され、投稿は変更されない状態になる。
- [ ] **AC-4-4** 自分のメッセージの削除確認画面から削除すると、元の会話に戻り、投稿者・日時・位置を保って「このメッセージは削除されました」という表示になる。

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
| TP-4-1 | 通常投稿だけの一覧、投稿者・日時・本文・日付区切り、表示範囲内の古い順と同時刻のID順 | 正常系 | [features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-11、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-05、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-15・Q-16 |
| TP-4-2 | 0件・10件・11件での初回最新10件と以前の10件の追加、表示済み範囲・読んでいた位置の維持 | 境界値 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-15、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-05、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 表示範囲 |
| TP-4-3 | 投稿・本文編集の必須・空白のみ・1,000文字／超過、静的な上限案内、入力エラー後の本文保持 | 境界値 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-05・入力チェック、[features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-12・F-20 |
| TP-4-4 | 本人だけが編集・削除でき、他人の投稿に操作を出さず、変更要求も拒否すること | 異常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) F-13・F-14・メッセージルート、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針 |
| TP-4-5 | インライン編集で周囲の会話を残し、保存・中止・編集期限なし・編集済み表示と、成功時の本文・編集日時・版の更新が一致すること | 正常系 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-05、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) messages、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 状態・編集競合 |
| TP-4-6 | 古い版の保存は409で停止し、現在本文と自分の入力を示し、確認後に成功した編集を採用すること。確認前の上書きや履歴復元を行わないこと | 異常系 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-30、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) 競合表示、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 本文編集の競合確認 |
| TP-4-7 | 入力エラー後も元の版を維持し、競合確認後に再度変更された場合も再確認し、競合の表示・中止だけでは保存しないこと | 異常系 | [behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 本文編集の競合確認、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) `expected_version` |
| TP-4-8 | SC-10のGETは確認表示だけで変更せず、確定・中止とも元のチャンネル／スレッドと表示範囲へ戻ること | 正常系 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-10、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 削除確認・戻り先 |
| TP-4-9 | 個別削除では元本文・投稿者・日時・位置・親子関係を保持し、会話には削除文言を表示する一方、元本文をHTML・編集欄・確認画面・検索・APIへ出さないこと | 正常系 | [data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) messages・保持方針、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 状態と取得、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-17・Q-24・Q-25 |
| TP-4-10 | 個別削除済みの編集・削除確認・再削除は409で本文を再表示せず、編集中でもチャンネル削除・権限喪失の拒否が競合確認に優先すること | 異常系 | [behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針・競合確認、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 権限ルール |
| TP-4-11 | SC-06の通常投稿・返信の入力エラーで303の戻り先に同じスレッドと表示範囲を保ち、送信した欄だけに本文のOld Inputとエラーを復元すること | 異常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 投稿ルート・復帰先、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-05・SC-06、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-34 |
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
- https://github.com/Tomoyuki-Kuge/teamchat-app/issues/3（メンバー管理）

起票順は認証→チャンネル→メンバー管理→メッセージ→スレッド→検索→公開API。前段で作成したデータ・認可・共通処理を利用し、機能をまたぐ受け入れ条件は関連機能が揃った時点で結合確認する。
