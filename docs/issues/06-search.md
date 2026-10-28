## 目的

設計書に従い、検索機能を実装する。

## 対象・確定した要件

- 対象画面: SC-07
- F-20（入力制約とエラー表示）・F-21（共通の画面・状態表現）は各機能へ適用する。

以下はfeatures.mdの対象行。リンク先はGitHubで参照できるURLに置き換えている。

| ID | 機能 | 対象・要件 | 根拠 | 関連する確認事項 |
|:--|:--|:--|:--|:--|
| F-17 | メッセージ検索 | ログインした社員が本文を検索する。検索用の値だけNFKC正規化・英字小文字化し、部分一致・複数語ANDと元本文の対応範囲の強調に使う。公開と参加中プライベートに限定し、検索語・件数・チャンネル名・投稿者・日時・本文を表示する。0件時は再検索を案内する。ページ送りは初回ID上限を維持し、再検索時に取り直す。範囲外ページは同じ条件の最後のページへ302で戻す | [仕様 §3-6](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/spec.md#3-6-検索)、[検索結果・0件](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/search.html)、[ガイド §4「空・0件」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/mockup/design-guide.md#4-状態の決まり)、通しレビュー後のユーザー決定 | Q-09、Q-10、Q-11、Q-14、Q-15、Q-31、Q-35、Q-36 |

## 現状・実装時の整理

通常投稿と返信の両方を対象とする。メンバー候補検索と比較用の正規化を揃え、本文検索だけに複数語ANDを適用する。

業務要件の未決事項はなし。Q-01〜Q-38の決定に従う。DBの具体的な制約・認証の保存方法などdata.md末尾の実装設計事項は、実装前に設計書へ具体化する。実装と設計が食い違う場合はdocs/design/を先に更新する。

## 受け入れ条件

出典: [acceptance.md「6. 検索」](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/acceptance.md)。以下の4行は原文をそのまま転記。

- [ ] **AC-6-1** 公開・参加中プライベート・未参加プライベートに同じ語の投稿を用意して検索すると、公開と参加中プライベートの未削除本文だけが結果の対象になる。
- [ ] **AC-6-2** 検索結果の通常投稿または返信を選択すると、通常投稿はチャンネル、返信はスレッドで、選んだ本文の位置を確認できる状態になる。
- [ ] **AC-6-3** 検索1ページ目の表示後に一致する投稿を新規作成し、元の結果で次へ進む操作をすると、その新着を含まず、検索語・並び順を引き継いだ結果になる。
- [ ] **AC-6-4** 未参加の高橋が「採用プロジェクト」の投稿だけに含まれる語で検索すると、結果は0件になり、そのチャンネル名や本文が表示されない状態になる。

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
| TP-6-1 | 通常投稿・返信の本文部分一致、検索語・本文のNFKC正規化と英字小文字化後の空白区切りの複数語AND、正規化後の`%`・`_`を検索語そのものとして扱うこと | 正常系 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-09・Q-35、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 検索用の比較と強調表示 |
| TP-6-2 | 公開・参加中プライベートだけを対象に、個別削除済みと削除済みチャンネルを件数計算・ページ分割の前に除外すること | 正常系 | [features.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/features.md) F-17、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) 取得条件、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 権限ルール |
| TP-6-3 | 検索語・総件数・チャンネル・投稿者・日時・本文・一致語強調、通常投稿／返信の結果から古い対象位置への移動 | 正常系 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-07、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) `focus`・結果の遷移、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-10・Q-11 |
| TP-6-4 | 空・空白だけの検索語は入力エラー、該当0件は正常な案内、10件／11件は1ページ10件になること | 境界値 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-07・入力チェック、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-15、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 0件の扱い |
| TP-6-5 | 新しい順／古い順と同時刻のID順、前へ・ページ番号・次へで検索語・並び順・初回ID上限を維持すること | 正常系 | [screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-07、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) ページ送り、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-15・Q-31 |
| TP-6-6 | 初回上限を閲覧可能な検索対象から求め、空なら0とし、新着は上限に含めず、再検索・検索語／並び順変更でページと上限を取り直すこと | 境界値 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 初回のID上限、[questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-31 |
| TP-6-7 | ページ送りでも現在の削除・本文・所属条件を再評価し、初回ID上限が完全なスナップショットや削除等によるずれの防止を保証しないこと | 正常系 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-31の注意点、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) ページ送り |
| TP-6-8 | 不正な検索・ページ・上限・表示位置パラメータを検証し、上限に他のIDを指定しても非公開情報や権限外の本文が取得できないこと | 異常系 | [permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) GETパラメータ・表示範囲・初回ID上限、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 例外方針 |
| TP-6-9 | 英字大小・全角半角・半角カナ・丸数字等の正規化で検索が一致し、文字数が変わる場合も元本文の対応範囲を強調し、保存値・一意性・認証の比較規則を変えないこと | 境界値 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-32・Q-35、[permissions-api.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/permissions-api.md) 検索用の比較と強調表示、[data.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/data.md) 取得条件 |
| TP-6-10 | 削除・所属変更等で有効な要求ページが範囲外になった場合、同じ検索語・並び順・初回ID上限で最後のページへ302で戻し、全体0件なら1ページ目で0件案内を表示すること | 境界値 | [questions.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/questions.md) Q-36、[screens.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/screens.md) SC-07、[behavior.md](https://github.com/Tomoyuki-Kuge/teamchat-app/blob/main/docs/design/behavior.md) 0件・範囲外の扱い |
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
- https://github.com/Tomoyuki-Kuge/teamchat-app/issues/3（メンバー管理）
- https://github.com/Tomoyuki-Kuge/teamchat-app/issues/4（メッセージ）
- https://github.com/Tomoyuki-Kuge/teamchat-app/issues/5（スレッド）

起票順は認証→チャンネル→メンバー管理→メッセージ→スレッド→検索→公開API。前段で作成したデータ・認可・共通処理を利用し、機能をまたぐ受け入れ条件は関連機能が揃った時点で結合確認する。
