#!/usr/bin/env bash
# Pulls the latest master and syncs it into the app dir.
# Runs as the fugitive user, invoked by the webhook service.
#
# Unlike the other sites, the nginx docroot is www/ *inside* the app dir, and
# two things live in the app dir that are not in the repo: vendor/ (composer)
# and keys.json (database credentials). Both are excluded below so --delete
# leaves them alone.
set -euo pipefail

REPO_URL="https://github.com/FugitiveTheGame/FugitiveWebsite.git"
BRANCH="master"
DEPLOY_DIR="/home/fugitive/deploy"
REPO_DIR="$DEPLOY_DIR/repo"
APP_DIR="/home/fugitive/site"
LOCK_FILE="$DEPLOY_DIR/.deploy.lock"
# Records the commit currently sitting in the app dir. This is deliberately not
# the checkout's HEAD: a fresh clone is already at origin/master while the app
# dir has never been synced, so comparing HEAD to origin would skip the first
# deploy.
STAMP_FILE="$DEPLOY_DIR/.deployed-sha"

FORCE=0
if [[ "${1:-}" == "--force" ]]; then
	FORCE=1
fi

log() { echo "[$(date -Is)] $*"; }

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
	log "another deploy is in progress, skipping this trigger"
	exit 0
fi

if [[ ! -d "$REPO_DIR/.git" ]]; then
	log "no checkout yet, cloning $REPO_URL"
	git clone --branch "$BRANCH" "$REPO_URL" "$REPO_DIR"
fi

git -C "$REPO_DIR" fetch --prune origin "$BRANCH"

target="$(git -C "$REPO_DIR" rev-parse "origin/$BRANCH")"
deployed="$(cat "$STAMP_FILE" 2>/dev/null || echo none)"

if [[ $FORCE -eq 0 && "$deployed" == "$target" ]]; then
	log "app dir already at ${target:0:8}, nothing to deploy"
	exit 0
fi

log "deploying ${deployed:0:8} -> ${target:0:8}"
git -C "$REPO_DIR" reset --hard "origin/$BRANCH"
git -C "$REPO_DIR" clean -fd

# Refuse to deploy something that isn't the site. Deliberately not a named
# file: this guard used to be "test -f www/index.html", which silently blocked
# every deploy the moment the pages moved to Twig and became index.php.
test -d "$REPO_DIR/www"
test -f "$REPO_DIR/composer.json"
if ! compgen -G "$REPO_DIR/www/index.*" >/dev/null; then
	log "no www/index.* in the checkout, refusing to deploy"
	exit 1
fi

composer_before=""
if [[ -f "$APP_DIR/composer.json" ]]; then
	composer_before="$(sha256sum "$APP_DIR/composer.json" | cut -d' ' -f1)"
fi

# No --delete-excluded here: vendor/, var/ and keys.json exist only in the app
# dir, and stripping them would take the site down.
rsync -a --delete --itemize-changes \
	--exclude='.git/' \
	--exclude='.github/' \
	--exclude='.idea/' \
	--exclude='.claude/' \
	--exclude='deploy/' \
	--exclude='vendor/' \
	--exclude='var/' \
	--exclude='keys.json' \
	--exclude='.editorconfig' \
	--exclude='.gitattributes' \
	--exclude='.gitignore' \
	"$REPO_DIR/" "$APP_DIR/"

# Twig compiles templates into here at runtime. Stale entries are keyed by
# template content, so a deploy just leaves them orphaned rather than stale.
mkdir -p "$APP_DIR/var/cache/twig"

composer_after="$(sha256sum "$APP_DIR/composer.json" | cut -d' ' -f1)"

if [[ ! -d "$APP_DIR/vendor" || "$composer_before" != "$composer_after" ]]; then
	log "installing composer dependencies"
	if [[ -f "$APP_DIR/composer.lock" ]]; then
		composer install --no-interaction --no-dev --optimize-autoloader \
			--working-dir "$APP_DIR"
	else
		# No lock committed yet; resolve and write one into the app dir so
		# subsequent deploys are reproducible.
		composer update --no-interaction --no-dev --optimize-autoloader \
			--working-dir "$APP_DIR"
	fi
else
	log "composer.json unchanged, keeping the existing vendor/"
fi

echo "$target" >"$STAMP_FILE"
log "deployed $(git -C "$REPO_DIR" log -1 --oneline)"
