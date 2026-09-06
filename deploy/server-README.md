# fugitivethegame.online on hydra

Installed from `deploy/install.sh` in the site repo. This file is a copy left at
`/home/fugitive/README.md` for whoever is looking at the box.

## Layout

| Path | What it is |
| --- | --- |
| `/home/fugitive/deploy/repo` | git checkout of `FugitiveTheGame/FugitiveWebsite`, branch `master` |
| `/home/fugitive/deploy/redeploy.sh` | pulls master and rsyncs it into the app dir |
| `/home/fugitive/deploy/.deployed-sha` | commit currently live in the app dir |
| `/home/fugitive/site` | app dir — the repo, minus `deploy/` |
| `/home/fugitive/site/www` | **nginx docroot** |
| `/home/fugitive/site/keys.json` | database credentials, `0640 fugitive:fugitive` |
| `/home/fugitive/site/vendor` | composer dependencies, not in git |
| `/home/fugitive/.htpasswd` | basic-auth file for `/management` |

The docroot is `www/` *inside* the app dir on purpose: the PHP resolves its
includes as `__DIR__/../`, so `includes/`, `templates/`, `vendor/` and
`keys.json` all sit one level above what the web server can reach.

## Deploys

A push to `master` hits `https://fugitivethegame.online/_deploy`, which nginx
proxies to the webhook daemon on `127.0.0.1:9189`, which runs `redeploy.sh`.

```sh
journalctl -u fugitive-webhook -f          # watch a deploy
sudo -u fugitive /home/fugitive/deploy/redeploy.sh --force   # deploy by hand
```

`redeploy.sh` re-runs `composer install` only when `composer.json` changes, and
`--exclude`s `vendor/`, `var/` and `keys.json` so `rsync --delete` cannot remove
them.

**The running `redeploy.sh` is a copy**, installed by `install.sh` and owned by
root. Editing `deploy/redeploy.sh` in the repo does not change what runs; a
deploy will not pick up its own new version. To update it, re-run `install.sh`,
which reinstalls the script from the staged kit and then forces a deploy.

## Database

MariaDB, database `fugitive`, user `fugitive` (localhost only, no DDL grants).
Two tables, both created by `deploy/schema.sql`:

- `feedback` — in-game feedback and crash reports, written by `www/feedback.php`
- `server_events` — `game_start` / `game_end` telemetry, written by `www/server_stats.php`

## The endpoints game clients depend on

Shipped builds call these, so they must keep working:

| URL | Handler |
| --- | --- |
| `/latest_version.json` | static file — the client update check |
| `/feedback` | `feedback.php` (POST) |
| `/server_stats` | `server_stats.php` (GET) |

The extensionless forms come from `try_files $uri $uri/ $uri.html $uri.php` in
the nginx site. The repo's `.htaccess` is an Apache leftover and does nothing
here; nginx refuses to serve dotfiles.

## Known gaps

- `repository.fugitivethegame.online` no longer resolves, so the in-game server
  browser is down and `/gamestats` shows "Servers online" as unavailable. The
  rest of that page comes from our own database and still works. Bringing the
  host back needs no site change.
- NotORM is unmaintained and emits a PHP 8 deprecation notice on every row
  access; the FPM pool sets `error_reporting = E_ALL & ~E_DEPRECATED` to keep
  those out of the log.
