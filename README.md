# Fugitive website

The site behind [fugitivethegame.online](https://fugitivethegame.online/), covering both
[Fugitive](https://stumpy-dog-studios.itch.io/fugitive) and
[Fugitive 3D](https://stumpy-dog-studios.itch.io/fugitive-3d).

## Layout

`www/` is the web root: point the vhost's `DocumentRoot` at it. Everything else in the repo
sits one level above the web root on purpose, because the PHP resolves it that way
(`require __DIR__ . '/../includes/utils.php'`), which keeps credentials and templates out of
reach of the web server.

| Path | What it is |
| --- | --- |
| `www/index.html` | Home page: both games |
| `www/fugitive.html` | Fugitive (2D) |
| `www/fugitive-3d.html` | Fugitive 3D |
| `www/privacy.html` | Privacy policy, linked from the Play Store listings |
| `www/assets/css/main.css` | HTML5 UP "Spectral", unmodified |
| `www/assets/css/fugitive.css` | The site skin, layered over Spectral |
| `www/assets/js/flashlight.js` | The pointer-tracked flashlight on the home hero |
| `www/gamestats.php` | Public stats page, served at `/gamestats` |
| `www/feedback.php` | Receives in-game feedback and crash reports |
| `www/server_stats.php` | Receives telemetry from game servers |
| `www/latest_version.json` | Version the game clients check against |
| `www/management/` | Admin dashboard, behind HTTP basic auth |
| `templates/` | Twig templates for the PHP pages |
| `includes/utils.php` | Database connection; reads `../keys.json` |

## Running it

The three HTML pages are static. To preview them:

    php -S localhost:8080 -t www

The PHP pages additionally need `vendor/` (`composer install`) and a `keys.json` in the repo
root holding the database credentials. Neither is in version control.

`.htaccess` maps extensionless URLs onto `.html` and `.php` files, so `/fugitive-3d` and
`/gamestats` both work. That rewrite needs Apache with `mod_rewrite`; the PHP built-in server
ignores it.
