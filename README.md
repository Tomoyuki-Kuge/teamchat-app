# チームチャットアプリ

## 何のアプリか

社内の連絡や相談を話題ごとのチャンネルにまとめ、過去の会話をあとから検索できるチャットアプリです。Laravel 10 と Laravel Fortify を使い、Docker / Laravel Sail で動かします。

PCブラウザ向けで、画面の最小幅は1,280pxです。リンクとフォーム送信で操作し、新着メッセージは画面を開き直して確認します。

## できること

| 機能 | 内容 |
| --- | --- |
| アカウント | 表示名・メールアドレス・パスワードでの新規登録、ログイン、ログアウト |
| チャンネル | 公開／プライベートのチャンネル作成、一覧・会話の閲覧。作成者による名前・説明の編集と削除 |
| メンバー管理 | プライベートチャンネルの作成者による、表示名・メールアドレスでの社員検索とメンバーの追加・解除 |
| メッセージ | 1,000文字までの投稿、自分の投稿の編集・削除。「編集済み」や「このメッセージは削除されました」の表示 |
| スレッド | メッセージに対する返信、返信件数の表示、右側のパネルでの会話の閲覧。返信も編集・削除可能 |
| 検索 | 自分が閲覧できる通常投稿・返信の本文検索。複数語のAND検索、一致箇所の強調、並べ替え、ページ送り |
| 公開API | 認証なしで公開チャンネルとメッセージを取得する、読み取り専用のJSON API |

公開チャンネルはログインした全員が読み書きでき、プライベートチャンネルは追加されたメンバーだけが利用できます。チャンネルの公開範囲は作成後に変更できません。

詳しい機能と対象範囲は[機能一覧](docs/design/features.md)を参照してください。自動更新、通知、ダイレクトメッセージ、ファイル添付、リアクション、未読・既読管理は対象外です。

## 動かす

Git と Docker（Docker Compose を含む）を用意し、Docker Desktop などを起動しておきます。以下は macOS / Linux / Windows（WSL2）のシェルで実行してください。ホスト側へのPHP・Node.jsのインストールは不要です。

標準では80番と3306番ポートを使います。別のSailアプリが使っている場合は、そのアプリのディレクトリで `./vendor/bin/sail stop` を実行してから始めてください。

**1. リポジトリを clone する**

```bash
git clone https://github.com/Tomoyuki-Kuge/teamchat-app.git
cd teamchat-app
```

**2. Composer の依存パッケージをインストールする**

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer install
```

**3. 設定ファイルを用意し、コンテナを起動する**

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail ps
```

初回はコンテナのビルドに時間がかかります。`sail ps` で `mysql` が `healthy` になってから次へ進みます。

**4. アプリケーションキー、テーブル、初期データを作る**

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

DBへの接続で `Connection refused` が出た場合は、MySQLの起動を待って同じコマンドを再実行してください。

ブラウザで <http://localhost> を開き、ログイン画面が表示されれば起動完了です。CSSは `public/css/app.css` を直接読み込むため、画面を使うための `npm install` や `npm run dev` は不要です。

作業を終えるときと、次に再開するときは次のコマンドを使います。停止してもDBのデータは残ります。

```bash
./vendor/bin/sail stop    # 停止
./vendor/bin/sail up -d   # 再開
```

## ログインする

<http://localhost/login> で、初期データに含まれる次のアカウントを使えます。パスワードは3人とも `password` です。

| 表示名 | メールアドレス | 作成したチャンネル |
| --- | --- | --- |
| 佐藤 太郎 | `sato@example.com` | 開発、採用プロジェクト |
| 鈴木 花子 | `suzuki@example.com` | 全体連絡 |
| 高橋 健 | `takahashi@example.com` | 雑談 |

初期チャンネルは「全体連絡」「開発」「雑談」が公開、「採用プロジェクト」がプライベートです。「採用プロジェクト」の初期メンバーは佐藤さんと鈴木さんで、高橋さんには表示されません。

公開チャンネルにはそれぞれ通常投稿10件、プライベートには4件と、スレッド返信が入っています。「編集済み」と削除済みの表示も確認できます。アカウント・所属・会話の定義は[初期データ](database/seeders/DatabaseSeeder.php)からたどれます。

自分のアカウントを作る場合は、ログイン画面の新規登録リンク、または <http://localhost/register> を開いてください。

## ひととおり触ってみる

1. **会話を読む**：佐藤さんでログインし、一覧から「開発」を開きます。初期の会話を読み、編集済み・削除済みの表示や返信件数を確認します。
2. **投稿・編集・削除する**：下の入力欄からメッセージを送信します。自分の投稿にマウスを重ねて「編集」から本文を変え、「保存」で編集済みになることを確認します。「削除」から確認画面へ進み、削除後の表示も見られます。
3. **スレッドで返信する**：初期投稿の「返信N件」や返信アイコンを押すと、右側にスレッドが開きます。返信欄に入力して「返信」を押し、通常投稿とは別に会話が続くことを確認します。
4. **検索する**：上部の「メッセージを検索」に `リリース` と入力して検索します。一致箇所の強調を確認し、結果から元の会話へ移動します。
5. **チャンネルを作る**：「チャンネル一覧を見る」から「チャンネルを作る」へ進み、試用チャンネルを作成します。作成者として名前・説明を編集できます。削除を試す場合は、編集画面の削除確認で試用チャンネル名を入力して確定します。
6. **プライベートの見え方を比べる**：上部の「ログアウト」から高橋さんに切り替え、「採用プロジェクト」が一覧に出ず、`面接` の検索でもその会話が出ないことを確認します。
7. **メンバーを追加・解除する**：佐藤さんに戻り、「採用プロジェクト」の「メンバー」で高橋さんを検索して追加します。高橋さんで入り直すとチャンネルと会話が見えるようになります。佐藤さんが「外す」を押せば、再び閲覧・投稿できなくなります。

## 公開API

ログインやAPIキーなしで利用できます。公開・未削除のチャンネルだけが対象で、投稿・編集・削除のAPIは提供していません。

| メソッド・パス | 取得内容 |
| --- | --- |
| `GET /api/channels` | 公開チャンネルの一覧。作成順、1ページ10件 |
| `GET /api/channels/{channel}/messages` | 指定チャンネルの通常投稿と返信。新しい順で、それぞれ1ページ5件 |

```bash
curl -sS -H 'Accept: application/json' 'http://localhost/api/channels'
curl -sS -H 'Accept: application/json' 'http://localhost/api/channels/1/messages'
```

2つ目のURLの `1` は、一覧で取得した公開チャンネルの `id` に置き換えてください。

レスポンスは `data`・`meta`・`links` を持ちます。メッセージは `data.messages` と `data.replies` に分かれます。続きがある場合は、`links.next` の相対URLを `http://localhost` に続けて取得してください。リンクには `page` と初回の取得範囲を保つ `until_id` が含まれます。最新を取り直す場合は、`until_id` を付けずに1ページ目から取得します。

プライベート・削除済み・存在しないチャンネルへのアクセスは、いずれも404です。削除済みメッセージの本文も返しません。返却項目・ページ分割・エラーの詳細は[公開APIの共通契約](docs/design/permissions-api.md#公開apiの共通契約)を参照してください。

## テストと整形

Sailのコンテナを起動した状態で、リポジトリのルートから実行します。

```bash
./vendor/bin/sail artisan test                        # 全テスト
./vendor/bin/sail artisan test --filter=ChannelTest    # 対象を絞る例
./vendor/bin/sail pint --test                         # PHPの書式を検査
./vendor/bin/sail pint                                # PHPを整形
```

PHPUnit 10 を使用しています。テストは `.env.example` と [phpunit.xml](phpunit.xml) を使い、開発用とは別のMySQLデータベース `testing` で実行します。`testing` はMySQLコンテナの初回初期化時に作成されます。

## 中身の地図

| 場所 | 読めること・担当すること |
| --- | --- |
| [docs/spec.md](docs/spec.md) | アプリの背景・要件・対象範囲 |
| [docs/design/](docs/design/README.md) | 機能、確認事項、画面、データ、権限・API、動作、受け入れ条件の設計 |
| [mockup/index.html](mockup/index.html) / [design-guide.md](mockup/design-guide.md) | 画面見本と、色・文字・余白・表示状態のルール |
| [routes/web.php](routes/web.php) / [api.php](routes/api.php) | 画面と公開APIの入口 |
| [app/Http/](app/Http/) | コントローラー、入力検証、HTTP応答 |
| [app/Actions/](app/Actions/) / [Policies/](app/Policies/) | 更新処理と認可 |
| [app/Models/](app/Models/) / [Support/](app/Support/) | データの関連、検索、会話の表示範囲などの処理 |
| [resources/views/](resources/views/) / [public/css/app.css](public/css/app.css) | Bladeの画面と、実際に読み込むCSS |
| [database/migrations/](database/migrations/) / [seeders/](database/seeders/) | テーブル定義と、初期アカウント・チャンネル・会話 |
| [tests/](tests/) / [phpunit.xml](phpunit.xml) | 自動テストと実行設定 |
| [compose.yaml](compose.yaml) / [.env.example](.env.example) | SailのPHP 8.5・MySQL 8.4構成と設定例 |
| [AGENTS.md](AGENTS.md) | 変更を加えるときのリポジトリ共通ルール |

仕様や動作を変更する場合は、関連する `docs/design/` を先に更新し、設計と実装を一致させます。
