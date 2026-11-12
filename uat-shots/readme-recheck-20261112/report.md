# README再現確認（独立したSail環境）

実施日：2026-11-12（日本時間）。対象はGitHubの `chore/handover`、コミット `9d68c7dc56715e4abaa12f7fb53b9813491865ed`。元のプロジェクトにあるREADMEを編集せず、cloneで取得したREADMEを検証した。

clone先：`/Users/dev/Sates/laravel-practice/readme-recheck-20261112/teamchat-app`。README手順3の設定例をそのまま反映し、プロジェクト名は `teamchat_readme_check`、URLは `http://localhost:8081`。

## 結果

**起動・停止・再開・DB再作成のコマンドはすべて終了コード0。「ひととおり触ってみる」は7件すべて満たす。詰まった箇所と、未記載なのに必要だった起動手順はなし。**

今回は独立したDBボリュームの新規作成、空DBからの全マイグレーション、初期データ投入を確認した。前回の「Nothing to migrate」ではなく、初回・DB再作成後ともに `Creating migration table` と9本のマイグレーションのDONEを観測した。通常クリック・マウスホバーも成功し、画面の選択や送信にDOM click・focusの補助は使っていない。

## 実行方法と範囲

- READMEに書かれた親フォルダの事前準備として、新しい親フォルダを作成した。cloneの行だけ `git clone -b chore/handover https://github.com/Tomoyuki-Kuge/teamchat-app.git` に置き換え、続く `cd teamchat-app` を実行した。
- 起動コマンドの引数・オプション・並び順は変更せず、追加の起動コマンドも使用していない。README手順3に明記された設定ファイルの編集として、`cp .env.example .env` の後に次の設定例をそのまま反映した。これは未記載の回避操作ではない。

```dotenv
COMPOSE_PROJECT_NAME=teamchat_readme_check
APP_PORT=8081
FORWARD_DB_PORT=3307
VITE_PORT=5174
APP_URL=http://localhost:8081
```

既存のAPP_URLを変更し、4項目を追記した。DB_HOST・DB_PORTなど、他の設定例の値は変更していない。元のプロジェクトの `.env` は操作していない。

- 元のSail環境はユーザーの直前の停止依頼で停止済みだった。今回、元の環境に追加の起動・停止・削除操作はしていない。
- README末尾の「DBを破棄してよい場合だけ」の分岐は、今回新しく作った検証用プロジェクトだけを対象に実行した。画面確認は、このDB再作成が終わってから開始した。
- README・来歴・Git状態の読み取り、画像・報告の保存は検証記録のために行い、アプリを起動するための追加手順とは分けた。`composer update`、`sail build`、`npm install`、`npm run dev`、`migrate:fresh`、chmod、追加のDocker診断・回避コマンドは実行していない。

## 「動かす」の実行記録

全コマンドの出力は [setup-log.txt](setup-log.txt) に保存した。

| READMEの順序 | 実行した内容 | 結果 |
| --- | --- | --- |
| 1 | GitHubからbranch指定でclone → `cd teamchat-app` | 終了0。取得コミットは `9d68c7d`。clone直後の作業ツリーに変更なし |
| 2 | 複数行の `docker run ... laravelsail/php82-composer:latest ... composer install` | 記載どおり実行し終了0。115パッケージの新規インストールとpackage discovery成功 |
| 3 | `cp .env.example .env` → 記載された設定例の編集 | 終了0、上記5項目を設定。起動コマンドへの追加オプションなし |
| 3 | `./vendor/bin/sail up -d` | 終了0。`teamchat_readme_check_sail-mysql` ボリューム、同名プロジェクトのネットワーク・2コンテナを新規作成。既存の `teamchat-app` とは別名 |
| 3 | `./vendor/bin/sail ps`、READMEの待機指示に従って同じコマンドを再実行 | どちらも終了0。最初はMySQLがhealth: starting、次の観測ではhealthy。公開ポートは8081・3307・5174 |
| 4 | `./vendor/bin/sail artisan key:generate` | 終了0、Application key set successfully |
| 4 | `./vendor/bin/sail artisan migrate --seed` | 終了0。空DBにマイグレーション管理表と全9マイグレーションを適用し、3つのSeederがすべてDONE |
| ブラウザ | READMEのポート読み替え指示どおり `http://localhost:8081` を開く | [ログイン画面](00_initial-login.png)を確認 |
| 停止・再開 | `./vendor/bin/sail stop` → `./vendor/bin/sail up -d` | 両方終了0。検証用の2コンテナを停止・再開 |
| DB再作成 | `./vendor/bin/sail down -v` → `./vendor/bin/sail up -d` → `./vendor/bin/sail ps` | すべて終了0。検証用ボリュームをRemoved後、同じ名前で新規Created。元のプロジェクトのボリュームは対象外 |
| DB再作成 | 同じ `sail ps` でhealthyを確認 → `./vendor/bin/sail artisan migrate --seed` | すべて終了0。マイグレーション管理表と全9マイグレーションを再度作成し、3つのSeederもDONE |

コンテナイメージやビルドキャッシュを全消去した検証ではない。既存のSailイメージを利用できたため、イメージを未取得の端末での初回ビルド時間・成功までは今回の結果に含めない。

## 詰まったところ・未記載の必要手順

| 確認した点 | 今回の観測 | 未記載の追加操作 |
| --- | --- | --- |
| 別cloneと元のDBの分離 | README記載のCOMPOSE_PROJECT_NAMEで独立したボリュームが新規作成され、空DBから適用できた | なし |
| 使用ポート・URL | 設定例の3ポートを使用でき、READMEの案内どおり8081へ読み替えてログイン・リンク・フォームを利用できた | なし |
| MySQLの起動待ち | 初回・再作成後ともstartingを観測。READMEどおり待って同じpsでhealthyを確認できた | なし。待機・再確認は既に記載された手順 |
| DBボリューム削除後 | upだけで終えず、README記載のmigrate --seedでテーブル・初期データを作り直せた | なし |
| `.env`参照制限、orphan警告、ブラウザー使用中、クリック・ホバー無反応 | この試行では発生しなかった | なし。過去の試行で観測した制約を今回の失敗として数えていない |

## 「ひととおり触ってみる」の画面確認

[playwright-uatスキル](../../.codex/skills/playwright-uat/SKILL.md)を使用。画面確認は16:24〜16:30（JST）、PC幅1280×900px。新しい隔離ブラウザーコンテキスト内でREADMEの順に進め、アカウント切り替えは記載どおり毎回ログアウト・ログインした。

操作はPlaywright MCPの通常クリック・ホバー・入力を使用した。evaluateは表示済みの本文・フォーカス・件数を読む用途だけで、DOMの変更、DOM click／focus、フォーム改変、直接POST／DELETE、URL手入力による導線の置き換えはしていない。

| READMEの手順 | 判定 | 観測した内容・画像 |
| --- | --- | --- |
| 1. 会話を読む | 満たす | 佐藤でログインし、一覧の「開発」の「開く」から会話へ移動。初期本文・編集済み・削除済み・返信3件／1件を確認。[初期会話](01_read-development.png) |
| 2. 投稿・編集・削除する | 満たす | 「README再検証1112の投稿です。」を送信し、佐藤の投稿ID51として表示。実際に[マウスホバーで操作欄を表示](02_hover-own-post.png)し、「編集」→本文変更→「保存」で[編集済み](03_edited-own-post.png)。削除確認画面の「削除する」で[元の位置に削除表示](04_deleted-own-post.png)。キーボードによる代替操作は使わず、ホバーの道順を確認した |
| 3. スレッドで返信する | 満たす | 初期の削除済み親ID20の「返信1件」をクリック。右の返信欄から「README再検証1112の返信です。」を送り、返信ID52が右側にだけ追加され、件数が2件になった。左の通常投稿には混ざらなかった。[スレッド返信](05_thread-reply.png) |
| 4. 検索する | 満たす | 上部から「リリース」を検索し、[1件の一致と強調](06_release-search.png)を確認。結果をクリックして[開発の親ID15の位置](07_search-to-conversation.png)へ戻れた。focus=15と対象本文・フォーカスも確認 |
| 5. チャンネルを作る | 満たす | 「チャンネル一覧を見る」→「チャンネルを作る」で[公開の試用チャンネルID5](08_created-channel.png)を作成。[名前・説明を変更](09_edited-channel.png)し、編集画面の削除確認で現在名「README再検証1112編集後」を入力して確定。[一覧から消えた状態](10_deleted-channel-list.png)を確認 |
| 6. プライベートの見え方を比べる | 満たす | 佐藤をログアウトし高橋でログイン。採用プロジェクトは[一覧に表示されず公開3件だけ](11_takahashi-public-only.png)。「面接」の検索は[0件](12_takahashi-private-search-zero.png)で、そのチャンネル名・本文も出なかった |
| 7. メンバーを追加・解除する | 満たす | 高橋をログアウトして佐藤に戻り、メンバー画面で高橋を検索。候補のラジオボタンを通常クリックで選び追加し、[メンバー3人](13_added-takahashi.png)。高橋で入り直すと一覧から[会話を閲覧](14_added-user-can-read.png)できた。佐藤に戻って高橋の「外す」を押し、[元の2人](15_removed-takahashi.png)へ。その後、高橋で入り直すと[再び公開3件だけ](16_removed-user-public-only.png)となり、採用の会話・投稿欄への通常導線がなくなった |

受け入れ条件全28件の再実施ではなく、READMEの7つの道順の確認。解除後の直接URL・直接投稿要求、全入力境界・同時操作などはこの試行では確認していない。自動テスト・Pintも今回の依頼範囲外として実行していない。

## 後片付け・終了時の状態

- 試用チャンネルID5は佐藤本人が画面から削除済み。初期4チャンネルは削除していない。
- 確認用の通常投稿ID51は手順2で削除済み。返信ID52も佐藤本人の画面で削除し、[削除表示と既存返信ID21の残存](17_cleanup-reply.png)を確認した。親ID20の返信件数は削除済み返信を含める設計に従い、1件から2件になったまま。
- 「README再検証1112」の[検索結果は0件](18_cleanup-search-zero.png)。削除された会話・チャンネルの保存行を物理削除する追加操作はしていない。
- 採用プロジェクトのメンバーは、元の佐藤・鈴木の2人に戻した。最終もログアウト済み。今回作った隔離コンテキストの1タブを閉じ、開始時の既存タブはそのまま残した。
- 検証用cloneと `teamchat_readme_check` のコンテナ・DBを残している。検証用アプリは `http://localhost:8081` で稼働する状態。元のSailプロジェクトには起動・停止・削除操作を追加していない。
- 新規cloneの追跡対象ファイルに差分なし。READMEどおり生成・設定した `.env` と依存パッケージはGit管理対象外。元のプロジェクトでは本報告・実行ログ・画像19枚を新規保存した。アプリのコード・README・設定例は変更せず、commit・pushもしていない。
