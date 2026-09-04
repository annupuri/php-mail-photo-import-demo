# php-mail-photo-import-demo

[![build](https://github.com/annupuri/php-mail-photo-import-demo/actions/workflows/build.yml/badge.svg)](https://github.com/annupuri/php-mail-photo-import-demo/actions/workflows/build.yml)

*English | [日本語](README.ja.md)*

A tiny photo gallery that fills itself from an **email inbox**. You log in with a
user id, press *Fetch mail*, and the app opens an IMAP mailbox, picks up every
message whose subject line equals your user id, decodes the image attachments,
stores them under `images/`, and records a row per photo in MySQL. From there you
can caption each photo, give it a place and a comment, or delete it.

## Layout

- `index.php` — login form; posts a user id to `main.php`
- `main.php` — the gallery: triggers the mail fetch, lists the caller's photos,
  handles per-photo delete
- `mail.php` — the IMAP side: open the mailbox, walk the messages, keep the ones
  addressed to this user, decode the attachments, write files, insert rows
- `edit.php` — edit one photo's title / place / comment
- `dao.php` — configuration constants plus `ImagesDAO`, a thin `mysqli`
  subclass that wraps every query as a prepared statement
- `i18n.php` + `lang/en.php`, `lang/ja.php` — the message catalogue, `t()`, the
  `h()` output-escaper and the `APP_TITLE` constant
- `schema.sql` — the single `images` table (`title`, `place`, `comment` columns)
- `tools/seed.php` — CLI helper that inserts one sample photo (skips the mail import)
- `images/` — where decoded attachments are written at runtime (git-ignored)

## Stack

PHP 8.3 with the `mysqli`, `mbstring` and `imap` extensions, and MySQL. No
framework and no Composer dependencies — `index` / `main` / `edit` / `mail`,
`dao.php`, `i18n.php`, and a `lang/` catalogue. `.tool-versions` pins PHP 8.3.33
for [asdf](https://asdf-vm.com).

## Design

**One prepared statement per operation.** `ImagesDAO extends mysqli`. Every
public method (`selectByUser`, `selectByIdAndUser`, `insert`, `update`, `delete`)
routes through a private `run($sql, $types, $params)` helper that calls
`prepare()`, `bind_param()` and `execute()`; the user id, ids and free-text
fields are always bound parameters, never string-interpolated into SQL. Reads
return a `mysqli_result` via `get_result()`, so the calling pages still iterate
with `fetch_array(MYSQLI_ASSOC)`.

**Escape on output.** Request values are read raw; every dynamic value is escaped
where it is printed, with `h()` (`htmlspecialchars`) in the templates. The two
message variables that intentionally carry `<br>` markup are the only exceptions.

**Mail as the ingest path.** `storeMail($user)` opens
`{_MAIL_SERVER:_MAIL_PORT/imap/norsh/notls}INBOX`, and `getAttachedMail()` keeps only
messages whose decoded subject equals `$user` and whose structure is
`multipart/mixed`. For each `JPEG` / `PNG` / `GIF` / `BMP` part it makes a
timestamp-based file name, `imap_fetchbody()` + `imap_base64()` to get the bytes,
writes the file, then `insertDB()` adds the row. Processed messages are flagged
with `imap_delete()` and removed with `imap_expunge()`.

**Ownership check on every mutation.** `edit.php` and the delete branch of
`main.php` always constrain by `id = ? AND user = ?`, so a request can only
touch rows that belong to the logged-in user id.

## Setup

1. **Install the toolchain.** With asdf: `asdf install` (reads `.tool-versions`).
   Everything except the *Fetch mail* button works without the `imap` extension,
   which the asdf PHP build does not bundle. To add it, compile just `ext/imap`
   against the installed PHP — Debian/Ubuntu no longer ship a `php-imap` package,
   and this asdf plugin's `PHP_CONFIGURE_OPTIONS` *replaces* its default configure
   flags rather than extending them, so a full rebuild would have to repeat the
   whole set:

   ```
   sudo apt-get install libc-client2007e-dev libkrb5-dev libssl-dev   # UW c-client + deps

   curl -LO https://www.php.net/distributions/php-8.3.33.tar.gz
   tar xf php-8.3.33.tar.gz && cd php-8.3.33/ext/imap
   "$(asdf where php)/bin/phpize"
   ./configure --with-php-config="$(asdf where php)/bin/php-config" \
     --with-imap --with-imap-ssl --with-kerberos
   make -j"$(nproc)" && make install
   echo 'extension=imap.so' > "$(asdf where php)/conf.d/imap.ini"
   asdf reshim php
   php -m | grep imap
   ```

2. **Start MySQL.** Any MySQL 8.0+ / MariaDB 10.4+ works — the SQL is
   version-agnostic. `asdf install mysql 8.0.33` (needs `asdf plugin add mysql`)
   only unpacks the official binary tarball; you initialise a data directory
   once and start the server yourself:

   ```
   MB=$(asdf where mysql)/bin
   DATADIR=$HOME/.local/share/mysql-uploader

   "$MB/mysqld" --no-defaults --initialize-insecure --datadir="$DATADIR"
   "$MB/mysqld" --no-defaults --datadir="$DATADIR" --socket=/tmp/mysql.sock --port=3306 &
   ```

   `--initialize-insecure` leaves `root` with no password; `--socket=/tmp/mysql.sock`
   matches PHP's built-in default, so `_DB_HOST=localhost` connects over that
   socket. Run `--initialize-insecure` only once — if it aborts with "data
   directory has files in it" it already succeeded, so skip straight to starting
   the server.

   The tarball does not bundle its shared-library dependencies. On a minimal
   Linux — including Ubuntu 24.04+ / WSL — install `libaio1t64`, `libnuma1` and
   `libncurses6` (the last one is for the `mysql` client). `libaio1t64` ships
   `libaio.so.1t64` but not the `libaio.so.1` soname the binary links against, so
   alias it once:

   ```
   sudo ln -s /usr/lib/x86_64-linux-gnu/libaio.so.1t64 /usr/lib/x86_64-linux-gnu/libaio.so.1
   sudo ldconfig
   ```

3. **Create the database and the app account:**

   ```
   mysql -S /tmp/mysql.sock -u root -e "CREATE DATABASE uploader DEFAULT CHARSET = utf8mb4;
     CREATE USER 'demo'@'localhost' IDENTIFIED BY 'demo';
     GRANT ALL PRIVILEGES ON uploader.* TO 'demo'@'localhost';"
   mysql -S /tmp/mysql.sock -u root uploader < schema.sql
   ```

   (Add `-p` instead if your server was set up with a `root` password.)

4. **Adjust `dao.php`** if your setup differs from the defaults:

   | Constant                    | Default             | Meaning                              |
   | --------------------------- | ------------------- | ------------------------------------ |
   | `_MAIL_SERVER`              | `127.0.0.1`         | IMAP host                            |
   | `_MAIL_PORT`                | `3143`              | IMAP port (GreenMail's test port)   |
   | `_MAIL_FLAGS`               | `/imap/norsh/notls` | `imap_open()` flags (`/imap/ssl`, …) |
   | `_MAIL_USER` / `_MAIL_PASS` | `demo` / `demo`     | mailbox login                        |
   | `_DB_HOST`                  | `localhost`         | MySQL host                           |
   | `_DB_USER` / `_DB_PASS`     | `demo` / `demo`     | MySQL login                          |
   | `_DB_NAME`                  | `uploader`          | database name                        |
   | `_IMAGE_DIR`                | `./images/`         | where photos are written             |

5. **(Optional) seed a sample photo** so the gallery is not empty:

   ```
   php tools/seed.php demo
   ```

## Run

```
php -S localhost:8000
```

Open <http://localhost:8000/>, enter a user id, and press *Log in*. Use *[Edit]*
to add a caption, place and comment, and *Delete* to remove a photo (the file
under `images/` is unlinked too). *Fetch mail* needs the `imap` extension and a
reachable mailbox — see below.

## Shutdown

Stop the PHP server (and GreenMail, if running) with `Ctrl-C`. Stop MySQL with:

```
"$(asdf where mysql)/bin/mysqladmin" --no-defaults -S /tmp/mysql.sock -u root shutdown
```

The data directory persists, so the next session skips step 2's
`--initialize-insecure` and just restarts the server:

```
"$(asdf where mysql)/bin/mysqld" --no-defaults \
  --datadir="$HOME/.local/share/mysql-uploader" --socket=/tmp/mysql.sock --port=3306 &
```

## Trying the mail import

The importer is an IMAP *consumer*; it never sends mail. `storeMail()` logs into
the mailbox at `_MAIL_SERVER:_MAIL_PORT`, keeps messages whose **subject equals
the logged-in user id** and that carry a `multipart/mixed` image attachment,
writes each `JPEG` / `PNG` / `GIF` / `BMP` part into `images/`, inserts a row,
then deletes the message from the mailbox.

To exercise it locally you need a plain (non-TLS) IMAP server on the configured
port. [GreenMail](https://greenmail-mail-test.github.io/greenmail/) is the
lightest option — one standalone jar, every protocol in memory. It needs a JRE
11+ (`asdf plugin add java && asdf install java temurin-21`, or any system JDK);
Java is only for this test double, so it is not pinned in `.tool-versions`. Grab
the jar from Maven Central and keep it outside the repo:

```
curl -LO https://repo1.maven.org/maven2/com/icegreen/greenmail-standalone/2.1.13/greenmail-standalone-2.1.13.jar
java -Dgreenmail.setup.test.all -Dgreenmail.users=demo:demo@localhost \
  -jar greenmail-standalone-2.1.13.jar
```

The `-D` options must come *before* `-jar`; anything after the jar name is passed
to the application, not the JVM, and GreenMail just prints its usage. Give the
user an `@localhost` address (`demo:demo@localhost`, not `demo:demo`) so it
matches the recipient you send to; IMAP login stays `demo` / `demo`. GreenMail
has no web console — port `8080` serves only a REST API, so check delivery with
`curl -s http://127.0.0.1:8080/api/user/demo@localhost/messages` (there is no
global `/api/messages`; `/api/user` lists the accounts).

That serves IMAP on `3143` and SMTP on `3025`, which is what `dao.php` already
points `_MAIL_SERVER` / `_MAIL_PORT` at (`127.0.0.1`, not `localhost` — the latter
may resolve to IPv6 `::1`, where GreenMail is not listening). Point them elsewhere
only if your IMAP server differs.

`getAttachedMail()` keeps a message only if its decoded subject is *exactly* the
login user id, its top level is `multipart/mixed`, and its image part is
`base64`-encoded. Ordinary mail clients tend to miss one of these, so send the
test message with a script:

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

Press *Fetch mail* and the photo shows up in the gallery; the importer then
deletes the message, so that `messages` list returns to empty. Dovecot with a Maildir
also works and can use the standard port 143. SMTP-only tools (MailHog, Mailpit)
will not work — the app speaks IMAP.

## Languages

The UI ships English and Japanese. `i18n.php` picks the language from
`?lang=en|ja`, then a `lang` cookie, then falls back to English; an explicit
`?lang=` is stored in the cookie. `t($key)` looks the string up in
`lang/<code>.php` (extra arguments go through `vsprintf`, so entries may hold
`%s`), and every page carries an *English | 日本語* switch that keeps the current
query string. To add a language, drop another `lang/<code>.php` next to the
existing two and list its code in `AVAILABLE_LANGS`.

## About

A learning project. It started as a coursework exercise for a university
web-programming class, built on top of a sample PHP uploader that was circulating
as course material. This repository keeps that original shape — plain PHP,
server-rendered pages, IMAP ingestion, a single MySQL table — while replacing the
string-concatenated SQL with prepared statements, swapping the site-specific mail
server and account names for placeholders, and lifting the UI strings into an
English / Japanese message catalogue.

## Scope

Not production software. The UI is unstyled HTML tables, there is no real
authentication (knowing a user id is enough to see and edit that id's photos),
sessions are not used, and errors are printed straight to the page. Run it
locally against a throwaway mailbox and database.

Bug reports via issues are welcome, but it is not under active feature
development.

## License

MIT License — see [LICENSE](LICENSE).
