# php-mail-photo-import-demo

[![build](https://github.com/annupuri/php-mail-photo-import-demo/actions/workflows/build.yml/badge.svg)](https://github.com/annupuri/php-mail-photo-import-demo/actions/workflows/build.yml)

*[English](README.md) | 日本語*

**メールの受信箱**から中身を取り込む小さな写真ギャラリーです。ユーザ ID でログインして
*Fetch mail* を押すと、アプリが IMAP のメールボックスを開き、件名がそのユーザ ID
に一致するメールをすべて拾い、添付画像をデコードして `images/` に保存し、写真 1 枚ごとに
MySQL へ 1 行記録します。あとは各写真にタイトル・場所・コメントを付けたり、削除したり
できます。

## 構成

- `index.php` — ログインフォーム。ユーザ ID を `main.php` に POST する
- `main.php` — ギャラリー本体。メール取得の起動、ログインユーザの写真一覧、
  写真ごとの削除を処理する
- `mail.php` — IMAP 側。メールボックスを開き、メッセージを走査し、このユーザ宛のものだけ
  残し、添付をデコードしてファイルを書き、行を INSERT する
- `edit.php` — 写真 1 枚のタイトル / 場所 / コメントを編集する
- `dao.php` — 設定用の定数と、すべてのクエリをプリペアドステートメントとして扱う
  薄い `mysqli` サブクラス `ImagesDAO`
- `i18n.php` + `lang/en.php` / `lang/ja.php` — メッセージカタログ、`t()`、出力
  エスケープ用 `h()`、`APP_TITLE` 定数
- `schema.sql` — `images` テーブル 1 つ(`title` / `place` / `comment` カラム)
- `tools/seed.php` — サンプル写真を 1 件 INSERT する CLI(メール取り込みを経由しない)
- `images/` — 実行時にデコードした添付が書き込まれる場所(git 管理外)

## 技術スタック

`mysqli` / `mbstring` 拡張を有効にした PHP 8.3 と MySQL。フレームワークはなし。
ページスクリプト(`index` / `main` / `edit` / `mail`)と `dao.php` / `i18n.php`、
それに `lang/` のカタログだけです。Composer 依存は `mail.php` が使う純 PHP の IMAP
クライアント [ImapEngine](https://github.com/DirectoryTree/ImapEngine) の 1 つだけで、
`imap` 拡張(PHP 8.4 で本体から分離)は不要です。
`.tool-versions` は [asdf](https://asdf-vm.com) 用に PHP 8.3.33 を pin しています。

## 設計

**1 操作につき 1 つのプリペアドステートメント。** `ImagesDAO extends mysqli` とし、
公開メソッド(`selectByUser` / `selectByIdAndUser` / `insert` / `update` / `delete`)は
すべて private の `run($sql, $types, $params)` を経由します。`run()` が `prepare()` /
`bind_param()` / `execute()` を呼び、ユーザ ID・ID・自由入力の各フィールドは常にバインド
パラメータで、SQL 文字列に埋め込むことはありません。参照系は `get_result()` で
`mysqli_result` を返すので、呼び出し側は従来どおり `fetch_array(MYSQLI_ASSOC)` で
反復できます。

**出力時エスケープ。** リクエスト値は生のまま受け取り、動的な値は表示する箇所で
テンプレートの `h()`(`htmlspecialchars`)によりエスケープします。意図的に `<br>` を
含む 2 つのメッセージ変数だけが例外です。

**取り込み経路としてのメール。** `storeMail($user)` が ImapEngine の `Mailbox` で
`_MAIL_SERVER:_MAIL_PORT` に接続して `INBOX` を開き、`getAttachedMail()` は、デコード後の
件名が `$user` に一致し、添付ファイルを持つメッセージだけを残します。`image/jpeg` /
`png` / `gif` / `bmp` の各添付についてタイムスタンプ由来のファイル名を作り、添付から
デコード済みのバイト列を取り出します。`mailRegister()` がファイルに書き、`insertDB()` で
行を追加します。処理済みメッセージは `markDeleted()` でフラグを立て、`expunge()` で削除
します。

**更新のたびに所有者チェック。** `edit.php` と `main.php` の削除分岐は常に
`id = ? AND user = ?` で絞り込むため、ログイン中のユーザ ID に属する行しか操作できません。

## セットアップ

1. **ツールチェーンと依存パッケージを入れる。** asdf なら `asdf install`
   (`.tool-versions` を読む)。続けてリポジトリのルートで Composer の依存パッケージを
   `vendor/`(git 管理外)に入れます:

   ```
   composer install
   ```

2. **MySQL を起動する。** MySQL 8.0 以降 / MariaDB 10.4 以降なら何でも可(SQL は
   バージョン非依存)。`asdf install mysql 8.0.33`(要 `asdf plugin add mysql`)は
   公式バイナリ tarball を展開するだけなので、データディレクトリの初期化と
   サーバ起動は自分で行います:

   ```
   MB=$(asdf where mysql)/bin
   DATADIR=$HOME/.local/share/mysql-uploader

   "$MB/mysqld" --no-defaults --initialize-insecure --datadir="$DATADIR"
   "$MB/mysqld" --no-defaults --datadir="$DATADIR" --socket=/tmp/mysql.sock --port=3306 &
   ```

   `--initialize-insecure` は `root` をパスワード無しにします。`--socket=/tmp/mysql.sock`
   は PHP の組み込み既定に合わせてあるので、`_DB_HOST=localhost` はこのソケット経由で
   つながります。`--initialize-insecure` は一度だけ実行します。「data directory has files
   in it」で中断した場合は初期化済みという意味なので、そのままサーバ起動に進んでください。

   tarball は共有ライブラリ依存を同梱しません。最小構成の Linux(Ubuntu 24.04+ / WSL を
   含む)では `libaio1t64` / `libnuma1` / `libncurses6` を入れてください(最後の 1 つは
   `mysql` クライアント用)。`libaio1t64` は `libaio.so.1t64` を提供しますが、バイナリが
   リンクする `libaio.so.1` という soname は無いので、一度だけ別名を張ります:

   ```
   sudo ln -s /usr/lib/x86_64-linux-gnu/libaio.so.1t64 /usr/lib/x86_64-linux-gnu/libaio.so.1
   sudo ldconfig
   ```

3. **データベースとアプリ用アカウントを作る:**

   ```
   mysql -S /tmp/mysql.sock -u root -e "CREATE DATABASE uploader DEFAULT CHARSET = utf8mb4;
     CREATE USER 'demo'@'localhost' IDENTIFIED BY 'demo';
     GRANT ALL PRIVILEGES ON uploader.* TO 'demo'@'localhost';"
   mysql -S /tmp/mysql.sock -u root uploader < schema.sql
   ```

   (`root` にパスワードを設定した場合は代わりに `-p` を付けてください。)

4. **既定と違う場合は `dao.php` を調整する:**

   | 定数                        | 既定値              | 意味                                |
   | --------------------------- | ------------------- | ----------------------------------- |
   | `_MAIL_SERVER`              | `127.0.0.1`         | IMAP ホスト                         |
   | `_MAIL_PORT`                | `3143`              | IMAP ポート(GreenMail のテストポート) |
   | `_MAIL_ENCRYPTION`          | `''`(平文)        | TLS なら `ssl` / `tls` / `starttls` |
   | `_MAIL_USER` / `_MAIL_PASS` | `demo` / `demo`     | メールボックスのログイン            |
   | `_DB_HOST`                  | `localhost`         | MySQL ホスト                        |
   | `_DB_USER` / `_DB_PASS`     | `demo` / `demo`     | MySQL のログイン                    |
   | `_DB_NAME`                  | `uploader`          | データベース名                      |
   | `_IMAGE_DIR`                | `./images/`         | 写真の書き込み先                    |

5. **(任意)サンプル写真を投入**してギャラリーを空でなくする:

   ```
   php tools/seed.php demo
   ```

## 実行

```
php -S localhost:8000
```

<http://localhost:8000/> を開き、ユーザ ID を入れて *Log in* を押します。*[Edit]* で
タイトル・場所・コメントを付け、*Delete* で写真を消します(`images/` 下のファイルも
unlink されます)。*Fetch mail* には到達可能なメールボックスが必要です(下記参照)。

## 停止

PHP サーバ(と、起動していれば GreenMail)は `Ctrl-C` で止めます。MySQL は次のコマンドで:

```
"$(asdf where mysql)/bin/mysqladmin" --no-defaults -S /tmp/mysql.sock -u root shutdown
```

データディレクトリは残るので、次回はセットアップ 2 の `--initialize-insecure` を飛ばして
サーバを再起動するだけです:

```
"$(asdf where mysql)/bin/mysqld" --no-defaults \
  --datadir="$HOME/.local/share/mysql-uploader" --socket=/tmp/mysql.sock --port=3306 &
```

## メール取り込みを試す

取り込み側は IMAP の *コンシューマ* で、メールの送信は一切しません。`storeMail()` は
`_MAIL_SERVER:_MAIL_PORT` のメールボックスにログインし、**件名がログイン中のユーザ ID
と一致**し、添付ファイルを持つメールだけを残して、各 `JPEG` / `PNG` / `GIF` / `BMP` の
添付を `images/` に書き、行を INSERT し、そのメールをメールボックスから削除します。

ローカルで試すには、設定ポートで平文(非 TLS)の IMAP サーバが要ります。
[GreenMail](https://greenmail-mail-test.github.io/greenmail/) が最も軽量です
(standalone jar 1 個・全プロトコルがインメモリ)。実行には JRE 11 以上が必要です
(`asdf plugin add java && asdf install java temurin-21` か、任意のシステム JDK)。
Java はこのテストダブル専用なので `.tool-versions` には pin していません。jar は
Maven Central から取得し、リポジトリの外に置きます:

```
curl -LO https://repo1.maven.org/maven2/com/icegreen/greenmail-standalone/2.1.13/greenmail-standalone-2.1.13.jar
java -Dgreenmail.setup.test.all -Dgreenmail.users=demo:demo@localhost \
  -jar greenmail-standalone-2.1.13.jar
```

`-D` オプションは `-jar` より**前**に置きます。jar 名より後ろの引数は JVM ではなく
アプリへ渡され、GreenMail は使い方を表示して終了します。ユーザは送信先と一致するよう
`@localhost` 付き(`demo:demo` ではなく `demo:demo@localhost`)にします。IMAP ログインは
`demo` / `demo` のままです。GreenMail に Web コンソールはなく、ポート `8080` は REST API
だけなので、配送確認は `curl -s http://127.0.0.1:8080/api/user/demo@localhost/messages`
で行います(全体を返す `/api/messages` は無く、`/api/user` はアカウント一覧です)。

これで IMAP が `3143`、SMTP が `3025` で立ち上がります。`dao.php` の `_MAIL_SERVER` /
`_MAIL_PORT` は既定でここ(`127.0.0.1` / `3143`)を指しています(`localhost` ではなく IP
なのは、`::1` に解決されて GreenMail が待ち受けていない場合があるため)。別の IMAP サーバ
を使うときだけ書き換えてください。

`getAttachedMail()` は、デコード後の件名がログイン用ユーザ ID と**完全一致**し、画像の
添付を持つメールだけを残します。両方を確実に満たすには、テストメールをスクリプトで送るのが
簡単です:

```
python3 - path/to/photo.jpg <<'PY'
import mimetypes, smtplib, sys
from email.message import EmailMessage
from pathlib import Path
img = Path(sys.argv[1])
sub = (mimetypes.guess_type(img.name)[0] or "image/jpeg").split("/")[1]
m = EmailMessage()
m["From"], m["To"], m["Subject"] = "tester@localhost", "demo@localhost", "demo"
m.set_content("test")
m.add_attachment(img.read_bytes(), maintype="image", subtype=sub, filename=img.name)
smtplib.SMTP("127.0.0.1", 3025).send_message(m)
PY
```

*Fetch mail* を押すとギャラリーに写真が現れます。取り込み後にそのメールは削除されるので
上記 `messages` の一覧は再び空になります。Maildir を使う Dovecot でもよく、標準ポート
143 が使えます。SMTP 専用ツール(MailHog、Mailpit)は IMAP を喋らないため使えません。

## 多言語対応

UI は英語と日本語を同梱しています。`i18n.php` が言語を `?lang=en|ja` → `lang` クッキー
→ 英語(既定)の順で決定し、明示的な `?lang=` はクッキーに保存します。`t($key)` が
`lang/<code>.php` から文字列を引き(追加引数は `vsprintf` に渡るので `%s` を含められます)、
各ページに現在のクエリ文字列を保った *English | 日本語* の切り替えを表示します。言語を
足すには `lang/<code>.php` を追加し、そのコードを `AVAILABLE_LANGS` に加えます。

## 概要

学習用プロジェクトです。もともとは大学の Web プログラミング講義の課題として、教材として
出回っていたサンプルの PHP アップローダを土台に作ったものです。このリポジトリでは、その
元の形(素の PHP、サーバレンダリングのページ、IMAP 取り込み、MySQL テーブル 1 つ)を
保ったまま、文字列連結の SQL をプリペアドステートメントに置き換え、特定サイト固有の
メールサーバ名とアカウント名をプレースホルダに差し替え、UI 文字列を英語 / 日本語の
メッセージカタログに切り出し、メール取り込みを `imap` 拡張から純 PHP の IMAP
クライアントに移しています。

## 位置づけ

本番用ソフトウェアではありません。UI はスタイルのない HTML テーブル、認証は実質なし
(ユーザ ID を知っていればその ID の写真を閲覧・編集できてしまう)、セッションは未使用、
エラーはそのままページに出力されます。使い捨てのメールボックスとデータベースに対して
ローカルで動かしてください。

Issue でのバグ報告は歓迎しますが、機能開発は行っていません。

## ライセンス

MIT License — [LICENSE](LICENSE) を参照してください。
