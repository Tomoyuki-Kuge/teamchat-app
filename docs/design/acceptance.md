# 受け入れ条件・テスト観点

[配置・分量の決まり](README.md)に従い、[機能一覧](features.md)、[確認事項](questions.md)、[画面設計](screens.md)、[データ設計](data.md)、[権限・ルーティング・API設計](permissions-api.md)、[振る舞い設計](behavior.md)を根拠に整理する。以下は実装後に確認する条件であり、確認済みの結果ではない。

## 受け入れ条件

### 確認の前提

PCブラウザで操作し、各行を個別に確認する。初期データの「採用プロジェクト」を基準に、3人を次の役として固定する。初期の作成者・所属は[機能一覧のF-22](features.md)に従う。この役分担は確認対象のチャンネル内での役であり、公開チャンネルでは3人とも閲覧・投稿できる。

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

### 1. 認証

- [ ] **AC-1-1** 登録画面で未登録の正しい情報を送信すると、自動ログインしてチャンネル一覧（`/channels`）を利用できる状態になる。
- [ ] **AC-1-2** 登録済みのメールアドレスと正しいパスワードでログインすると、チャンネル一覧に移動し、上部に自分の表示名がある状態になる。
- [ ] **AC-1-3** 誤ったパスワードでログインすると、「メールアドレスまたはパスワードが正しくありません」と表示され、未ログインのままになる。
- [ ] **AC-1-4** 上部からログアウトしてチャンネル一覧のURLに再アクセスすると、ログイン画面になる。

### 2. チャンネル

- [ ] **AC-2-1** 名前・説明・公開範囲を指定してチャンネルを作成すると、作成したチャンネルが開き、作成者自身が閲覧・投稿できる状態になる。
- [ ] **AC-2-2** 作成者が名前と説明を編集して保存すると、公開範囲を保ったまま変更後の表示になる。
- [ ] **AC-2-3** チャンネル名を空のまま作成を送信すると、「チャンネル名を入力してください」と表示され、作成されない状態になる。
- [ ] **AC-2-4** 作成者が削除確認で対象名を正しく入力して削除すると、チャンネル一覧に戻り、そのチャンネルのURLを開いても会話が表示されない状態になる。

### 3. メンバー管理

- [ ] **AC-3-1** プライベートチャンネルの作成者が未参加社員の表示名またはメールアドレスの一部で検索すると、その社員が追加候補になる。
- [ ] **AC-3-2** 作成者が候補を選んで追加し、追加された社員でチャンネルを開く操作をすると、その社員も閲覧・投稿できる状態になる。
- [ ] **AC-3-3** 作成者がメンバーを外し、外された社員で同じチャンネルのURLに再アクセスすると、チャンネルの内容が表示されない状態になる。
- [ ] **AC-3-4** 作成者がメンバー一覧を表示すると、自分の「外す」は無効で「作成者は外せません」と表示され、解除できない状態になる。

### 4. メッセージ

- [ ] **AC-4-1** 閲覧・投稿できるチャンネルで本文を入力して送信すると、自分の名前・投稿日時・本文が通常投稿の一覧に表示される状態になる。
- [ ] **AC-4-2** 自分のメッセージを編集して保存すると、その位置に変更後の本文と「編集済み」が表示される状態になる。
- [ ] **AC-4-3** 佐藤が鈴木の投稿の削除確認URLにアクセスすると、「この操作はできません」と表示され、投稿は変更されない状態になる。
- [ ] **AC-4-4** 自分のメッセージの削除確認画面から削除すると、元の会話に戻り、投稿者・日時・位置を保って「このメッセージは削除されました」という表示になる。

### 5. スレッド

- [ ] **AC-5-1** 親メッセージの返信件数からスレッドを開く操作をすると、チャンネルの右側に親・全返信件数・返信一覧が表示される状態になる。
- [ ] **AC-5-2** スレッド内で本文を入力して返信すると、通常投稿には混ざらず、そのスレッドの返信一覧と全返信件数に反映された状態になる。
- [ ] **AC-5-3** 返信のある親を本人が個別削除してスレッドを開き直す操作をすると、親は削除表示になり、既存の返信を読んで新たに返信できる状態になる。
- [ ] **AC-5-4** スレッドで1,001文字の本文を送信すると、「メッセージは1,000文字以内で入力してください」と表示され、スレッドを開いた返信欄に本文が残り、未投稿の状態になる。

### 6. 検索

- [ ] **AC-6-1** 公開・参加中プライベート・未参加プライベートに同じ語の投稿を用意して検索すると、公開と参加中プライベートの未削除本文だけが結果の対象になる。
- [ ] **AC-6-2** 検索結果の通常投稿または返信を選択すると、通常投稿はチャンネル、返信はスレッドで、選んだ本文の位置を確認できる状態になる。
- [ ] **AC-6-3** 検索1ページ目の表示後に一致する投稿を新規作成し、元の結果で次へ進む操作をすると、その新着を含まず、検索語・並び順を引き継いだ結果になる。
- [ ] **AC-6-4** 未参加の高橋が「採用プロジェクト」の投稿だけに含まれる語で検索すると、結果は0件になり、そのチャンネル名や本文が表示されない状態になる。

### 7. 公開API

- [ ] **AC-7-1** 未ログインで `/api/channels` にアクセスすると、公開チャンネルだけが作成順で最大10件、`data`・`meta`・`links`を持つJSONになる。
- [ ] **AC-7-2** 未ログインで `/api/channels/{channel}/messages` に公開チャンネルのIDでアクセスすると、未削除の通常投稿と返信が新しい順で最大5件ずつ、別の配列に入ったJSONになる。
- [ ] **AC-7-3** 未ログインでチャンネル一覧を経由せずプライベートのメッセージAPIに直接アクセスすると、名前や本文を含まない `NOT_FOUND` のエラーJSONになる。
- [ ] **AC-7-4** 「採用プロジェクト」の作成者である佐藤がログインした状態で `/api/channels` にアクセスすると、自分が作ったプライベートも含まれず、公開チャンネルだけのJSONになる。

---

## テスト観点

ここには実装・テストで網羅する観点だけを置く。個別の手順・期待値の全組み合わせは展開しない。各行の区分は、その観点の主な確認対象を表す。元になった設計書のF番号・SC番号・Q番号は、対応する記述をたどるために付記する。

### 認証

| ID | 何を確かめるか | 区分 | 元になった設計書 |
|:--|:--|:--|:--|
| TP-1-1 | 自己登録、確認用パスワードの一致、自動ログイン、登録後のチャンネル一覧への遷移 | 正常系 | [features.md](features.md) F-01、[screens.md](screens.md) SC-02、[permissions-api.md](permissions-api.md) 認証ルート |
| TP-1-2 | 登録の必須・メール形式・確認不一致、表示名30文字／超過、パスワード7文字／8文字・128文字／129文字、正規化後のメール255文字／256文字 | 境界値 | [screens.md](screens.md) 入力チェック、[features.md](features.md) F-20、[questions.md](questions.md) Q-37・Q-38 |
| TP-1-3 | メールの前後空白・改行除去とドメイン小文字化が保存・重複確認・ログインで一致し、ローカル部の大小・幅・Unicode差とパスワードは変換しないこと | 境界値 | [questions.md](questions.md) Q-32、[screens.md](screens.md) 入力チェック、[data.md](data.md) users |
| TP-1-4 | 正規化後のメール重複と同時登録の一意制約違反が、登録済みの入力エラーになること | 異常系 | [screens.md](screens.md) SC-02、[data.md](data.md) users、[behavior.md](behavior.md) 例外方針 |
| TP-1-5 | ログイン・期限切れ後の再ログインは常に一覧へ遷移し、ログイン済みのログイン／登録画面へのアクセスも一覧へ戻ること | 正常系 | [permissions-api.md](permissions-api.md) 認証ルート、[behavior.md](behavior.md) 例外方針 |
| TP-1-6 | 存在しないメールと誤パスワードの失敗文言が共通で、認証されないこと | 異常系 | [features.md](features.md) F-02、[screens.md](screens.md) SC-01 |
| TP-1-7 | ログアウト後に認証が必要な画面を利用できないこと | 正常系 | [features.md](features.md) F-03、[permissions-api.md](permissions-api.md) 共通ルール |
| TP-1-8 | 未ログイン・ログイン期限切れはログイン画面へ302で移動し、未完了の変更を保存しないこと | 異常系 | [behavior.md](behavior.md) 例外方針、[permissions-api.md](permissions-api.md) 共通ルール |
| TP-1-9 | CSRF不正・期限切れは419で送信を拒否し、ログイン期限切れと重なってもCSRF検証を優先して自動再送しないこと | 異常系 | [behavior.md](behavior.md) 例外方針、[permissions-api.md](permissions-api.md) 共通ルール |
| TP-1-10 | メールは登録・ログインとも正規化後の上限を検証して切り捨てず、パスワードは前後空白・大小・Unicodeを変換せず全体をArgon2idで保存・照合し、ログインに登録用の最小8文字検証を流用しないこと | 正常系 | [data.md](data.md) users、[screens.md](screens.md) 入力チェック、[questions.md](questions.md) Q-37・Q-38 |

### チャンネル

| ID | 何を確かめるか | 区分 | 元になった設計書 |
|:--|:--|:--|:--|
| TP-2-1 | 一覧・サイドバーの作成順、名前・説明・種別・作成者、公開の `#` とプライベートの鍵、切り替えと空状態 | 正常系 | [features.md](features.md) F-04、[screens.md](screens.md) SC-03・共通表示 |
| TP-2-2 | 作成時の公開範囲と作成者の自動参加、作成成功時の遷移、作成・編集のキャンセル | 正常系 | [features.md](features.md) F-05・F-07、[screens.md](screens.md) SC-04・SC-08、[data.md](data.md) channels・channel_user |
| TP-2-3 | 公開はログイン社員全員が利用でき、未参加プライベートは一覧・サイドバーに出ず、直接URLも存在しない対象と同じ404になること | 異常系 | [permissions-api.md](permissions-api.md) F-04〜F-06・権限ルール、[behavior.md](behavior.md) 例外方針 |
| TP-2-4 | 作成者だけが編集・削除できること、公開範囲は変更できないこと、表示中の他人のチャンネルの変更要求は403になること | 異常系 | [features.md](features.md) F-07・F-08、[permissions-api.md](permissions-api.md) 権限・チャンネルルート |
| TP-2-5 | 名前の空・空白のみ・50文字／超過、説明の未入力・200文字／超過、公開範囲の必須・選択肢制約 | 境界値 | [screens.md](screens.md) 入力チェック、[features.md](features.md) F-20 |
| TP-2-6 | 名前は原文で比較し、前後空白・英字大小・全角半角・Unicode差を別名として扱い、編集時は自分自身を重複対象から外すこと | 境界値 | [questions.md](questions.md) Q-32、[data.md](data.md) channels、[screens.md](screens.md) 入力チェック |
| TP-2-7 | 公開・プライベートをまたぐ同名登録と同時作成の衝突が「使用できない名前です」になり、既存チャンネルの情報を返さないこと | 異常系 | [questions.md](questions.md) Q-28、[screens.md](screens.md) 入力チェック、[behavior.md](behavior.md) 例外方針 |
| TP-2-8 | 削除確認の対象名・通常投稿数・返信数・取り消せない旨、個別削除済みを含む実件数、確認用の名前の完全一致、キャンセルで変更しないこと | 境界値 | [screens.md](screens.md) SC-08、[questions.md](questions.md) Q-21・Q-32、[permissions-api.md](permissions-api.md) チャンネルルート |
| TP-2-9 | チャンネル削除が未編集・編集済み・個別削除済みの全投稿・全返信を終了させ、削除表示・返信件数も画面・検索・APIに残さず、以後の閲覧・変更を404にすること | 正常系 | [behavior.md](behavior.md) メッセージの状態・例外方針、[features.md](features.md) F-08、[questions.md](questions.md) Q-26 |
| TP-2-10 | 削除時にチャンネル・所属・会話の行と本文、個別の編集・削除の値を保持し、同じ名前を新しいチャンネルIDで再利用できること | 正常系 | [data.md](data.md) 保持方針・channels、[questions.md](questions.md) Q-26・Q-27 |

### メンバー管理

| ID | 何を確かめるか | 区分 | 元になった設計書 |
|:--|:--|:--|:--|
| TP-3-1 | プライベートの作成者だけが管理でき、公開チャンネルには管理操作がなく、他人の管理要求が拒否されること | 異常系 | [permissions-api.md](permissions-api.md) F-09・F-10・メンバールート、[screens.md](screens.md) SC-09 |
| TP-3-2 | 人数・表示名・メール・作成者表示、検索用のNFKC・英字小文字化後の入力語全体の表示名またはメールへの部分一致、複数語ANDを使わないこと、表示名→ID順、既存メンバーの候補除外 | 正常系 | [features.md](features.md) F-09、[questions.md](questions.md) Q-29・Q-35、[screens.md](screens.md) SC-09 |
| TP-3-3 | 空・空白だけの検索では候補を出さず、一致0件は正常な案内になること | 境界値 | [questions.md](questions.md) Q-29、[screens.md](screens.md) SC-09 |
| TP-3-4 | 選択した社員の追加と即時の閲覧・投稿許可、追加後のメンバー表示と候補からの除外 | 正常系 | [features.md](features.md) F-10、[permissions-api.md](permissions-api.md) メンバールート |
| TP-3-5 | 解除直後に通常画面・スレッド・検索・投稿の権限を失い、本人のアカウントと過去の投稿・返信は保持されること | 正常系 | [data.md](data.md) channel_user・CRUD表、[permissions-api.md](permissions-api.md) 権限ルール、[behavior.md](behavior.md) 権限再確認 |
| TP-3-6 | 作成者解除の無効表示とサーバーでの403、未選択・未登録社員の追加時の入力エラー | 異常系 | [screens.md](screens.md) SC-09・入力チェック、[permissions-api.md](permissions-api.md) メンバールート |
| TP-3-7 | 既存メンバーの再追加・解除済みメンバーの再解除は変更なしで成功し、所属の重複を作らないこと | 境界値 | [permissions-api.md](permissions-api.md) メンバールート、[data.md](data.md) channel_user |

### メッセージ

| ID | 何を確かめるか | 区分 | 元になった設計書 |
|:--|:--|:--|:--|
| TP-4-1 | 通常投稿だけの一覧、投稿者・日時・本文・日付区切り、表示範囲内の古い順と同時刻のID順 | 正常系 | [features.md](features.md) F-11、[screens.md](screens.md) SC-05、[questions.md](questions.md) Q-15・Q-16 |
| TP-4-2 | 0件・10件・11件での初回最新10件と以前の10件の追加、表示済み範囲・読んでいた位置の維持 | 境界値 | [questions.md](questions.md) Q-15、[screens.md](screens.md) SC-05、[permissions-api.md](permissions-api.md) 表示範囲 |
| TP-4-3 | 投稿・本文編集の必須・空白のみ・1,000文字／超過、静的な上限案内、入力エラー後の本文保持 | 境界値 | [screens.md](screens.md) SC-05・入力チェック、[features.md](features.md) F-12・F-20 |
| TP-4-4 | 本人だけが編集・削除でき、他人の投稿に操作を出さず、変更要求も拒否すること | 異常系 | [permissions-api.md](permissions-api.md) F-13・F-14・メッセージルート、[behavior.md](behavior.md) 例外方針 |
| TP-4-5 | インライン編集で周囲の会話を残し、保存・中止・編集期限なし・編集済み表示と、成功時の本文・編集日時・版の更新が一致すること | 正常系 | [screens.md](screens.md) SC-05、[data.md](data.md) messages、[behavior.md](behavior.md) 状態・編集競合 |
| TP-4-6 | 古い版の保存は409で停止し、現在本文と自分の入力を示し、確認後に成功した編集を採用すること。確認前の上書きや履歴復元を行わないこと | 異常系 | [questions.md](questions.md) Q-30、[screens.md](screens.md) 競合表示、[behavior.md](behavior.md) 本文編集の競合確認 |
| TP-4-7 | 入力エラー後も元の版を維持し、競合確認後に再度変更された場合も再確認し、競合の表示・中止だけでは保存しないこと | 異常系 | [behavior.md](behavior.md) 本文編集の競合確認、[permissions-api.md](permissions-api.md) `expected_version` |
| TP-4-8 | SC-10のGETは確認表示だけで変更せず、確定・中止とも元のチャンネル／スレッドと表示範囲へ戻ること | 正常系 | [screens.md](screens.md) SC-10、[permissions-api.md](permissions-api.md) 削除確認・戻り先 |
| TP-4-9 | 個別削除では元本文・投稿者・日時・位置・親子関係を保持し、会話には削除文言を表示する一方、元本文をHTML・編集欄・確認画面・検索・APIへ出さないこと | 正常系 | [data.md](data.md) messages・保持方針、[behavior.md](behavior.md) 状態と取得、[questions.md](questions.md) Q-17・Q-24・Q-25 |
| TP-4-10 | 個別削除済みの編集・削除確認・再削除は409で本文を再表示せず、編集中でもチャンネル削除・権限喪失の拒否が競合確認に優先すること | 異常系 | [behavior.md](behavior.md) 例外方針・競合確認、[permissions-api.md](permissions-api.md) 権限ルール |
| TP-4-11 | SC-06の通常投稿・返信の入力エラーで303の戻り先に同じスレッドと表示範囲を保ち、送信した欄だけに本文のOld Inputとエラーを復元すること | 異常系 | [permissions-api.md](permissions-api.md) 投稿ルート・復帰先、[screens.md](screens.md) SC-05・SC-06、[questions.md](questions.md) Q-34 |

### スレッド

| ID | 何を確かめるか | 区分 | 元になった設計書 |
|:--|:--|:--|:--|
| TP-5-1 | 返信操作・件数からの開閉、右パネルの親・全返信件数・返信一覧、通常投稿とは別の入力欄と投稿先 | 正常系 | [features.md](features.md) F-15・F-16、[screens.md](screens.md) SC-06 |
| TP-5-2 | 返信0件・10件・11件、初回最新10件と以前の返信追加、親・全返信件数・表示済み範囲・位置の維持 | 境界値 | [questions.md](questions.md) Q-15、[screens.md](screens.md) SC-06、[permissions-api.md](permissions-api.md) 返信表示範囲 |
| TP-5-3 | 親の個別削除後も既存返信の閲覧・追加ができ、削除済み返信は元の位置に残り、全返信件数にも含まれること | 正常系 | [questions.md](questions.md) Q-05・Q-06、[behavior.md](behavior.md) 代表シーケンス・状態と取得 |
| TP-5-4 | 返信の投稿・編集・削除にも通常投稿と同じ本文制約・本人制限・競合確認・削除表示を適用すること | 正常系 | [screens.md](screens.md) SC-06、[permissions-api.md](permissions-api.md) メッセージルート、[behavior.md](behavior.md) メッセージの状態 |
| TP-5-5 | 同じチャンネルの通常投稿だけを親にでき、別チャンネル・返信を親にする要求や不正な親子関係を拒否すること | 異常系 | [data.md](data.md) messages、[permissions-api.md](permissions-api.md) スレッドルート、[behavior.md](behavior.md) 例外方針 |
| TP-5-6 | 親削除時は返信の本文・状態を変えず、チャンネル削除時は親・全返信の状態にかかわらず閲覧・追加・編集・削除を終了すること | 正常系 | [behavior.md](behavior.md) 状態遷移・代表シーケンス、[data.md](data.md) 保持方針、[questions.md](questions.md) Q-26 |

### 検索

| ID | 何を確かめるか | 区分 | 元になった設計書 |
|:--|:--|:--|:--|
| TP-6-1 | 通常投稿・返信の本文部分一致、検索語・本文のNFKC正規化と英字小文字化後の空白区切りの複数語AND、正規化後の`%`・`_`を検索語そのものとして扱うこと | 正常系 | [questions.md](questions.md) Q-09・Q-35、[permissions-api.md](permissions-api.md) 検索用の比較と強調表示 |
| TP-6-2 | 公開・参加中プライベートだけを対象に、個別削除済みと削除済みチャンネルを件数計算・ページ分割の前に除外すること | 正常系 | [features.md](features.md) F-17、[data.md](data.md) 取得条件、[permissions-api.md](permissions-api.md) 権限ルール |
| TP-6-3 | 検索語・総件数・チャンネル・投稿者・日時・本文・一致語強調、通常投稿／返信の結果から古い対象位置への移動 | 正常系 | [screens.md](screens.md) SC-07、[permissions-api.md](permissions-api.md) `focus`・結果の遷移、[questions.md](questions.md) Q-10・Q-11 |
| TP-6-4 | 空・空白だけの検索語は入力エラー、該当0件は正常な案内、10件／11件は1ページ10件になること | 境界値 | [screens.md](screens.md) SC-07・入力チェック、[questions.md](questions.md) Q-15、[behavior.md](behavior.md) 0件の扱い |
| TP-6-5 | 新しい順／古い順と同時刻のID順、前へ・ページ番号・次へで検索語・並び順・初回ID上限を維持すること | 正常系 | [screens.md](screens.md) SC-07、[permissions-api.md](permissions-api.md) ページ送り、[questions.md](questions.md) Q-15・Q-31 |
| TP-6-6 | 初回上限を閲覧可能な検索対象から求め、空なら0とし、新着は上限に含めず、再検索・検索語／並び順変更でページと上限を取り直すこと | 境界値 | [permissions-api.md](permissions-api.md) 初回のID上限、[questions.md](questions.md) Q-31 |
| TP-6-7 | ページ送りでも現在の削除・本文・所属条件を再評価し、初回ID上限が完全なスナップショットや削除等によるずれの防止を保証しないこと | 正常系 | [questions.md](questions.md) Q-31の注意点、[permissions-api.md](permissions-api.md) ページ送り |
| TP-6-8 | 不正な検索・ページ・上限・表示位置パラメータを検証し、上限に他のIDを指定しても非公開情報や権限外の本文が取得できないこと | 異常系 | [permissions-api.md](permissions-api.md) GETパラメータ・表示範囲・初回ID上限、[behavior.md](behavior.md) 例外方針 |
| TP-6-9 | 英字大小・全角半角・半角カナ・丸数字等の正規化で検索が一致し、文字数が変わる場合も元本文の対応範囲を強調し、保存値・一意性・認証の比較規則を変えないこと | 境界値 | [questions.md](questions.md) Q-32・Q-35、[permissions-api.md](permissions-api.md) 検索用の比較と強調表示、[data.md](data.md) 取得条件 |
| TP-6-10 | 削除・所属変更等で有効な要求ページが範囲外になった場合、同じ検索語・並び順・初回ID上限で最後のページへ302で戻し、全体0件なら1ページ目で0件案内を表示すること | 境界値 | [questions.md](questions.md) Q-36、[screens.md](screens.md) SC-07、[behavior.md](behavior.md) 0件・範囲外の扱い |

### 公開API

| ID | 何を確かめるか | 区分 | 元になった設計書 |
|:--|:--|:--|:--|
| TP-7-1 | 認証なしの2つのGET、作成者がログインして呼び出しても公開だけの返却範囲、JSONのContent-Type、チャンネルの作成順、投稿・返信の新しい順と同時刻のID順 | 正常系 | [permissions-api.md](permissions-api.md) 公開APIの共通ルール・各エンドポイント、[features.md](features.md) F-18・F-19 |
| TP-7-2 | `data`・`meta`・`links`、各返却項目・型・null・JSTのISO 8601日時が契約に一致し、メール・個別削除済みの元本文・内部の版番号等を混入させないこと | 正常系 | [permissions-api.md](permissions-api.md) 出力・JSON例、[questions.md](questions.md) Q-12 |
| TP-7-3 | チャンネル10件／超過、通常投稿と返信は各5件／超過の独立枠で、一方の不足を他方で埋めず、同じページ番号を使うこと | 境界値 | [permissions-api.md](permissions-api.md) ページ分割、[questions.md](questions.md) Q-15 |
| TP-7-4 | 個別削除済みを集計前に除外した各総数、2枠の大きい方で決まる最終ページ、同じ上限を保つ前後リンク | 正常系 | [permissions-api.md](permissions-api.md) `meta`・`links`・ページ分割、[data.md](data.md) 取得条件 |
| TP-7-5 | 空一覧・片方だけ空・範囲外ページを200と所定の空配列で返し、最終ページは最低1とすること | 境界値 | [permissions-api.md](permissions-api.md) 空・ページ範囲外の例、[behavior.md](behavior.md) 0件の扱い |
| TP-7-6 | 別ページ・個別削除済みの親でも未削除返信に親IDを返し、親の本文は漏らさず、返信件数は削除済み・初回上限より新しい返信も含む現在の全件数になること | 正常系 | [permissions-api.md](permissions-api.md) メッセージ出力・返信件数、[questions.md](questions.md) Q-06・Q-13・Q-31 |
| TP-7-7 | 初回上限は返せる公開対象だけから求め、メッセージ・返信は同じ上限を使い、ページ移動で維持し、上限なしで開き直すと取り直すこと | 正常系 | [permissions-api.md](permissions-api.md) 初回のID上限、[questions.md](questions.md) Q-31 |
| TP-7-8 | 上限0と正のページ番号、負数・空・小数・配列・数値以外などの不正値を区別し、不正クエリは422の項目別エラーになること | 境界値 | [permissions-api.md](permissions-api.md) 入力・422のJSON例 |
| TP-7-9 | プライベート・削除済み・不存在・不正形式のチャンネルIDを同じ404にし、クエリ検証より先に対象の公開可否を判定して名前・存在・本文を返さないこと | 異常系 | [permissions-api.md](permissions-api.md) 共通ルール・404、[behavior.md](behavior.md) 例外方針 |
| TP-7-10 | HEADはGET相当のステータス・ヘッダーで本文なし、変更メソッド等は405と `Allow: GET, HEAD` になり、APIから変更できないこと | 異常系 | [permissions-api.md](permissions-api.md) HTTPメソッド・405、[features.md](features.md) 今回作らないもの |
| TP-7-11 | 404・405・422・500を `error.code`・`message`・`details` のJSONで返し、成功用フィールド・HTML・ログインへのリダイレクト・内部例外を混ぜないこと | 異常系 | [permissions-api.md](permissions-api.md) エラー形式・各JSON例、[behavior.md](behavior.md) 例外方針 |

### 共通

| ID | 何を確かめるか | 区分 | 元になった設計書 |
|:--|:--|:--|:--|
| TP-C-1 | PC幅1,280pxでの共通配置・見本の状態表現・日本時間表示、静的な上限案内、JavaScriptなしの画面再表示と手動更新 | 正常系 | [features.md](features.md) F-20・F-21、[screens.md](screens.md) 共通表示、[questions.md](questions.md) Q-16・Q-18 |
| TP-C-2 | 初期3アカウントと4チャンネルの作成者・所属、公開ごとの投稿量・著者と日時の分散、返信付き・編集済み・削除済み・プライベートの初期会話 | 正常系 | [features.md](features.md) F-22・F-23、[data.md](data.md) 初期データ |
| TP-C-3 | GETで変更せず、フォームのメソッド・CSRF・サーバー側の投稿者／作成者確定・対象IDの整合・安全な内部戻り先を検証すること | 異常系 | [permissions-api.md](permissions-api.md) 共通ルール・ルート・戻り先、[behavior.md](behavior.md) 例外方針 |
| TP-C-4 | 入力エラーの項目表示・文言・非機密の再入力値保持、変更フォームの303と不正GETの422、パスワード・確認用パスワードを入力エラー後に引き継がず、保存はハッシュだけにすること | 異常系 | [screens.md](screens.md) 入力チェック、[behavior.md](behavior.md) 例外方針、[data.md](data.md) users |
| TP-C-5 | 変更時の権限再確認とトランザクション、チャンネル削除・所属解除・投稿・編集・削除の競合順序、失敗時に一部だけ保存しないこと | 異常系 | [behavior.md](behavior.md) 保存・同時操作・失敗、[data.md](data.md) 制約・CRUD表 |
| TP-C-6 | DB障害は500で内部情報を伏せ、ログにも本文・パスワードを残さず、確定後の応答失敗では保存を巻き戻さず自動再送しないこと | 異常系 | [behavior.md](behavior.md) 例外方針・保存後の失敗、[permissions-api.md](permissions-api.md) エラー |
