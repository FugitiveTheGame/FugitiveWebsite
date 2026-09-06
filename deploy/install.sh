#!/usr/bin/env bash
# Installs fugitivethegame.online on hydra: site user, PHP-FPM pool, MariaDB
# database, nginx vhost, TLS, and the webhook-driven redeploy pipeline.
#
# Run with sudo. Safe to re-run; the webhook secret, the database password and
# the admin password are all preserved across runs.
#
# Mirrors the darkrockstudios.com / adamwbrown.me setup, with two differences:
# the docroot is www/ inside the checkout rather than the checkout itself, and
# there is a composer step plus a database.
set -euo pipefail

SITE_USER="fugitive"
DOMAIN="fugitivethegame.online"
DEPLOY_DIR="/home/$SITE_USER/deploy"
APP_DIR="/home/$SITE_USER/site"
REPO_URL="https://github.com/FugitiveTheGame/FugitiveWebsite.git"
BRANCH="master"
CONF_DIR="/etc/fugitive-webhook"
SECRET_FILE="$CONF_DIR/secret"
NGINX_SITE="/etc/nginx/sites-available/$DOMAIN"
NGINX_SNIPPET="/etc/nginx/snippets/fugitive-deploy.conf"
PHP_VERSION="8.5"
PHP_POOL="/etc/php/$PHP_VERSION/fpm/pool.d/$SITE_USER.conf"
KEYS_FILE="$APP_DIR/keys.json"
HTPASSWD="/home/$SITE_USER/.htpasswd"
DB_NAME="fugitive"
DB_USER="fugitive"
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $EUID -ne 0 ]]; then
	echo "error: must run as root (use sudo)" >&2
	exit 1
fi

step() { printf '\n==> %s\n' "$*"; }

step "Creating the $SITE_USER user"
if id "$SITE_USER" >/dev/null 2>&1; then
	echo "user already exists"
else
	useradd --create-home --shell /bin/bash "$SITE_USER"
	echo "created $SITE_USER"
fi

step "Installing packages"
missing=()
command -v webhook >/dev/null || missing+=(webhook)
command -v rsync >/dev/null || missing+=(rsync)
command -v git >/dev/null || missing+=(git)
command -v htpasswd >/dev/null || missing+=(apache2-utils)
if ((${#missing[@]})); then
	apt-get update -qq
	apt-get install -y "${missing[@]}"
else
	echo "webhook, rsync, git and htpasswd all present"
fi

# The Debian webhook package ships its own always-on instance bound to :9000.
# We run our own unit instead, so make sure the stock one cannot collide.
if systemctl list-unit-files webhook.service >/dev/null 2>&1; then
	step "Disabling the stock webhook.service"
	systemctl disable --now webhook.service || true
fi

step "Installing composer"
if command -v composer >/dev/null; then
	echo "already installed: $(composer --version)"
else
	tmp="$(mktemp -d)"
	curl -sS https://getcomposer.org/installer -o "$tmp/composer-setup.php"
	php "$tmp/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
	rm -rf "$tmp"
	echo "installed: $(composer --version)"
fi

step "Creating $DEPLOY_DIR and $APP_DIR"
install -d -o "$SITE_USER" -g "$SITE_USER" -m 0755 "$DEPLOY_DIR"
install -d -o "$SITE_USER" -g "$SITE_USER" -m 0750 "$APP_DIR"
install -o "$SITE_USER" -g "$SITE_USER" -m 0750 "$SRC_DIR/redeploy.sh" "$DEPLOY_DIR/redeploy.sh"
install -o "$SITE_USER" -g "$SITE_USER" -m 0644 \
	"$SRC_DIR/server-README.md" "/home/$SITE_USER/README.md"

# nginx (www-data) has to traverse into the docroot.
chmod o+x "/home/$SITE_USER" "$APP_DIR"

step "Ensuring the git checkout exists"
if [[ ! -d "$DEPLOY_DIR/repo/.git" ]]; then
	sudo -u "$SITE_USER" -H git clone --branch "$BRANCH" "$REPO_URL" "$DEPLOY_DIR/repo"
else
	echo "checkout already present at $DEPLOY_DIR/repo"
	sudo -u "$SITE_USER" git -C "$DEPLOY_DIR/repo" remote set-url origin "$REPO_URL"
fi

step "Setting up the database"
if mysql -N -B -e "SHOW DATABASES LIKE '$DB_NAME';" | grep -q "$DB_NAME"; then
	echo "database $DB_NAME already exists"
else
	mysql -e "CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
	echo "created database $DB_NAME"
fi

if [[ -f "$KEYS_FILE" ]]; then
	DB_PASS="$(python3 -c "import json,sys; print(json.load(open(sys.argv[1]))['mysql']['password'])" "$KEYS_FILE")"
	echo "reusing the password from the existing keys.json"
else
	DB_PASS="$(openssl rand -base64 30 | tr -d '/+=' | head -c 32)"
	echo "generated a new database password"
fi

# Piped in on stdin rather than passed with -e, so the password never appears
# in the process list.
mysql <<SQL
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
echo "database user $DB_USER ready"

step "Applying the schema"
mysql "$DB_NAME" <"$SRC_DIR/schema.sql"
mysql -N -B "$DB_NAME" -e "SHOW TABLES;" | sed 's/^/  table: /'

step "Writing $KEYS_FILE"
# Written via python so the password never reaches the process list.
DB_USER="$DB_USER" DB_PASS="$DB_PASS" DB_NAME="$DB_NAME" python3 - "$KEYS_FILE" <<'PY'
import json, os, sys
with open(sys.argv[1], "w") as f:
    json.dump({"mysql": {
        "database": os.environ["DB_NAME"],
        "user": os.environ["DB_USER"],
        "password": os.environ["DB_PASS"],
    }}, f, indent=2)
    f.write("\n")
PY
chown "$SITE_USER:$SITE_USER" "$KEYS_FILE"
chmod 0640 "$KEYS_FILE"

step "Setting up the /management password"
if [[ -f "$HTPASSWD" ]]; then
	echo "reusing the existing $HTPASSWD"
	ADMIN_PASS=""
else
	ADMIN_PASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 20)"
	htpasswd -bc "$HTPASSWD" admin "$ADMIN_PASS" >/dev/null
	echo "created $HTPASSWD for user 'admin'"
fi
# nginx reads this as www-data.
chown "root:www-data" "$HTPASSWD"
chmod 0640 "$HTPASSWD"

step "Installing the PHP-FPM pool"
install -m 0644 "$SRC_DIR/php-fpm-pool.conf" "$PHP_POOL"
# Graceful reload (SIGUSR2) rather than restart: the adam and darkrockstudios
# pools live in the same master process and should not drop requests for this.
systemctl reload "php$PHP_VERSION-fpm"
echo "pool listening on /run/php/php$PHP_VERSION-fpm-$SITE_USER.sock"

step "Establishing the webhook secret"
install -d -o root -g root -m 0755 "$CONF_DIR"
if [[ -f "$SECRET_FILE" ]]; then
	echo "reusing the existing secret"
else
	openssl rand -hex 32 >"$SECRET_FILE"
	chmod 0600 "$SECRET_FILE"
	echo "new secret generated"
fi
SECRET="$(cat "$SECRET_FILE")"

step "Writing $CONF_DIR/hooks.yaml"
# Substituted via python to keep the secret out of the process list.
SECRET="$SECRET" python3 - "$SRC_DIR/hooks.yaml" "$CONF_DIR/hooks.yaml" <<'PY'
import os, sys
src, dst = sys.argv[1], sys.argv[2]
with open(src) as f:
    content = f.read()
with open(dst, "w") as f:
    f.write(content.replace("__WEBHOOK_SECRET__", os.environ["SECRET"]))
PY
chown "root:$SITE_USER" "$CONF_DIR/hooks.yaml"
chmod 0640 "$CONF_DIR/hooks.yaml"

step "Installing the systemd unit"
install -m 0644 "$SRC_DIR/fugitive-webhook.service" /etc/systemd/system/fugitive-webhook.service
systemctl daemon-reload
systemctl enable --now fugitive-webhook.service
systemctl restart fugitive-webhook.service

step "Wiring up nginx"
install -d -m 0755 /etc/nginx/snippets
install -m 0644 "$SRC_DIR/nginx-deploy-snippet.conf" "$NGINX_SNIPPET"
if [[ ! -f "$NGINX_SITE" ]]; then
	install -m 0644 "$SRC_DIR/nginx-site.conf" "$NGINX_SITE"
	echo "site config installed"
else
	# certbot rewrites this file after first install; never clobber it.
	echo "site config already present, leaving it alone"
fi
ln -sf "$NGINX_SITE" "/etc/nginx/sites-enabled/$DOMAIN"

step "Running the first deploy"
sudo -u "$SITE_USER" -H "$DEPLOY_DIR/redeploy.sh" --force

step "Validating and reloading nginx"
nginx -t
systemctl reload nginx

step "Obtaining the TLS certificate"
if [[ -d "/etc/letsencrypt/live/$DOMAIN" ]]; then
	echo "certificate already present"
elif command -v certbot >/dev/null; then
	certbot --nginx -n --agree-tos -d "$DOMAIN" -d "www.$DOMAIN" \
		|| echo "warning: certbot failed — site is up on http only; fix and re-run" >&2
else
	echo "warning: certbot not found — site is up on http only" >&2
fi

step "Smoke test"
# certbot has already added the 80->443 redirect by this point, so follow it.
# --resolve keeps both hops pointed at this box rather than out over the internet.
probe() {
	curl -sk -o /dev/null -w '%{http_code}' -L \
		--resolve "$DOMAIN:80:127.0.0.1" \
		--resolve "$DOMAIN:443:127.0.0.1" \
		"http://$DOMAIN$1" 2>/dev/null || echo ERR
}
for path in / /fugitive /fugitive-3d /latest_version.json /gamestats; do
	printf '  %-24s %s\n' "$path" "$(probe "$path")"
done
printf '  %-24s %s (401 means the password is working)\n' "/management/" "$(probe /management/)"

cat <<EOF

============================================================
 Server side is ready.

 Site:         https://$DOMAIN
 Docroot:      $APP_DIR/www
 Deploy log:   journalctl -u fugitive-webhook -f
 Manual sync:  sudo -u $SITE_USER $DEPLOY_DIR/redeploy.sh --force

 Webhook URL:  https://$DOMAIN/_deploy
 Content type: application/json
 Events:       push only

 Register the webhook from your workstation with:

   gh api repos/FugitiveTheGame/FugitiveWebsite/hooks \\
     -f name=web \\
     -F active=true \\
     -f 'events[]=push' \\
     -f config[url]=https://$DOMAIN/_deploy \\
     -f config[content_type]=json \\
     -f config[secret]='$SECRET'
EOF

if [[ -n "$ADMIN_PASS" ]]; then
	cat <<EOF

 /management login — save this now, it is not shown again:
     user: admin
     pass: $ADMIN_PASS
EOF
fi

echo "============================================================"
