#!/usr/bin/env bash

set -euo pipefail

log() {
    printf '[bootstrap] %s\n' "$1"
}

fail() {
    printf '[bootstrap][error] %s\n' "$1" >&2
    exit 1
}

require_root() {
    if [ "${EUID}" -ne 0 ]; then
        fail "Run as root (sudo bash docker/bootstrap-vps.sh)."
    fi
}

require_ubuntu() {
    if [ ! -f /etc/os-release ]; then
        fail "Cannot detect OS; /etc/os-release not found."
    fi

    # shellcheck disable=SC1091
    . /etc/os-release

    if [ "${ID:-}" != "ubuntu" ]; then
        fail "This script supports Ubuntu only. Detected: ${ID:-unknown}."
    fi
}

ensure_safe_path() {
    local target_path="$1"

    case "$target_path" in
        "/"|""|"."|"..")
            fail "Unsafe path: '$target_path'."
            ;;
    esac
}

apt_install() {
    local package_name="$1"

    if ! dpkg -s "$package_name" >/dev/null 2>&1; then
        apt-get install -y "$package_name"
    fi
}

ensure_deploy_user() {
    if id "$DEPLOY_USER" >/dev/null 2>&1; then
        log "User '$DEPLOY_USER' already exists."
    else
        log "Creating user '$DEPLOY_USER'."
        adduser --disabled-password --gecos "" "$DEPLOY_USER"
    fi

    usermod -aG docker "$DEPLOY_USER"
}

ensure_deploy_ssh_key() {
    local ssh_dir
    local auth_keys

    if [ -z "$DEPLOY_SSH_PUBLIC_KEY" ]; then
        log "DEPLOY_SSH_PUBLIC_KEY not provided. Skipping SSH key setup."
        return
    fi

    ssh_dir="/home/$DEPLOY_USER/.ssh"
    auth_keys="$ssh_dir/authorized_keys"

    mkdir -p "$ssh_dir"
    touch "$auth_keys"

    if ! grep -Fqx "$DEPLOY_SSH_PUBLIC_KEY" "$auth_keys"; then
        log "Adding SSH key to $auth_keys."
        printf '%s\n' "$DEPLOY_SSH_PUBLIC_KEY" >> "$auth_keys"
    else
        log "SSH key already present in $auth_keys."
    fi

    chown -R "$DEPLOY_USER:$DEPLOY_USER" "$ssh_dir"
    chmod 700 "$ssh_dir"
    chmod 600 "$auth_keys"
}

ensure_directories() {
    ensure_safe_path "$APP_PATH"
    ensure_safe_path "$PREVIEWS_PATH"

    mkdir -p "$APP_PATH/docker"
    chown -R "$DEPLOY_USER:$DEPLOY_USER" "$APP_PATH"

    if [ "$SETUP_PREVIEWS" = "1" ]; then
        mkdir -p "$PREVIEWS_PATH"
        chown -R "$DEPLOY_USER:$DEPLOY_USER" "$PREVIEWS_PATH"
    fi
}

ensure_docker() {
    if command -v docker >/dev/null 2>&1; then
        log "Docker already installed."
    else
        log "Installing Docker."
        curl -fsSL https://get.docker.com | sh
    fi

    if ! command -v docker >/dev/null 2>&1; then
        fail "Docker installation failed."
    fi

    if ! command -v docker-compose >/dev/null 2>&1; then
        true
    fi
}

ensure_ufw() {
    if [ "$ENABLE_UFW" != "1" ]; then
        log "UFW setup disabled by ENABLE_UFW=0."
        return
    fi

    apt_install ufw

    ufw allow OpenSSH >/dev/null 2>&1 || true
    ufw allow 80/tcp >/dev/null 2>&1 || true
    ufw allow 443/tcp >/dev/null 2>&1 || true

    ufw --force enable >/dev/null 2>&1 || true
    log "UFW configured (OpenSSH, 80, 443)."
}

install_cloudflared() {
    local temp_deb="/tmp/cloudflared.deb"

    if command -v cloudflared >/dev/null 2>&1; then
        log "cloudflared already installed."
        return
    fi

    log "Installing cloudflared."
    curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb -o "$temp_deb"
    dpkg -i "$temp_deb"
    rm -f "$temp_deb"
}

configure_cloudflared() {
    if [ "$ENABLE_CLOUDFLARED" != "1" ]; then
        log "Cloudflared setup disabled by ENABLE_CLOUDFLARED=0."
        return
    fi

    install_cloudflared

    if [ -n "$CLOUDFLARE_TUNNEL_ID" ] && [ -n "$CLOUDFLARE_CREDENTIALS_FILE" ]; then
        mkdir -p "$(dirname "$CLOUDFLARE_TUNNEL_CONFIG_PATH")"

        cat > "$CLOUDFLARE_TUNNEL_CONFIG_PATH" <<EOF
tunnel: $CLOUDFLARE_TUNNEL_ID
credentials-file: $CLOUDFLARE_CREDENTIALS_FILE

ingress:
  - service: http_status:404
EOF

        log "Wrote cloudflared config to $CLOUDFLARE_TUNNEL_CONFIG_PATH."
    else
        log "CLOUDFLARE_TUNNEL_ID or CLOUDFLARE_CREDENTIALS_FILE missing. Skipping config file generation."
    fi

    if [ "$INSTALL_CLOUDFLARED_SERVICE" = "1" ]; then
        cloudflared service install || true
        systemctl enable cloudflared || true
        systemctl restart cloudflared || true
        log "cloudflared service enabled/restarted."
    fi

    if [ "$ALLOW_DEPLOY_RESTART_CLOUDFLARED" = "1" ]; then
        printf '%s\n' "$DEPLOY_USER ALL=(ALL) NOPASSWD: /bin/systemctl restart cloudflared" > /etc/sudoers.d/cloudflared
        chmod 440 /etc/sudoers.d/cloudflared
        log "Sudoers rule added: $DEPLOY_USER can restart cloudflared."
    fi
}

copy_compose_files() {
    local script_dir

    script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

    cp "$script_dir/production-compose.yml" "$APP_PATH/docker/production-compose.yml"

    if [ -f "$script_dir/preview-compose.yml" ]; then
        cp "$script_dir/preview-compose.yml" "$APP_PATH/docker/preview-compose.yml"
    fi

    if [ -f "$script_dir/hml-mysql-compose.yml" ]; then
        cp "$script_dir/hml-mysql-compose.yml" "$APP_PATH/docker/hml-mysql-compose.yml"
    fi

    chown -R "$DEPLOY_USER:$DEPLOY_USER" "$APP_PATH/docker"
    log "Compose files copied to $APP_PATH/docker."
}

start_hml_mysql() {
    if [ "$START_HML_MYSQL" != "1" ]; then
        log "Shared HML MySQL startup disabled by START_HML_MYSQL=0."
        return
    fi

    if [ ! -f "$APP_PATH/docker/hml-mysql-compose.yml" ]; then
        fail "Missing $APP_PATH/docker/hml-mysql-compose.yml"
    fi

    log "Starting shared HML MySQL."
    HML_MYSQL_ROOT_PASSWORD="$HML_MYSQL_ROOT_PASSWORD" HML_MYSQL_PORT="$HML_MYSQL_PORT" \
        docker compose -f "$APP_PATH/docker/hml-mysql-compose.yml" up -d
}

print_summary() {
    cat <<EOF

Bootstrap complete.

Deploy user: $DEPLOY_USER
App path: $APP_PATH
Previews path: $PREVIEWS_PATH
UFW enabled: $ENABLE_UFW
Cloudflared enabled: $ENABLE_CLOUDFLARED
Shared HML MySQL started: $START_HML_MYSQL

Next checks:
  - ssh -i <key> $DEPLOY_USER@<VPS_IP>
  - docker ps
  - [optional] sudo systemctl status cloudflared
EOF
}

main() {
    require_root
    require_ubuntu

    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt_install curl
    apt_install ca-certificates

    ensure_docker
    ensure_deploy_user
    ensure_deploy_ssh_key
    ensure_directories
    copy_compose_files
    ensure_ufw
    configure_cloudflared
    start_hml_mysql
    print_summary
}

DEPLOY_USER="${DEPLOY_USER:-deploy}"
DEPLOY_SSH_PUBLIC_KEY="${DEPLOY_SSH_PUBLIC_KEY:-}"
APP_PATH="${APP_PATH:-/opt/app}"
PREVIEWS_PATH="${PREVIEWS_PATH:-/opt/previews}"

SETUP_PREVIEWS="${SETUP_PREVIEWS:-1}"
ENABLE_UFW="${ENABLE_UFW:-1}"
ENABLE_CLOUDFLARED="${ENABLE_CLOUDFLARED:-0}"
INSTALL_CLOUDFLARED_SERVICE="${INSTALL_CLOUDFLARED_SERVICE:-1}"
ALLOW_DEPLOY_RESTART_CLOUDFLARED="${ALLOW_DEPLOY_RESTART_CLOUDFLARED:-1}"

CLOUDFLARE_TUNNEL_ID="${CLOUDFLARE_TUNNEL_ID:-}"
CLOUDFLARE_CREDENTIALS_FILE="${CLOUDFLARE_CREDENTIALS_FILE:-}"
CLOUDFLARE_TUNNEL_CONFIG_PATH="${CLOUDFLARE_TUNNEL_CONFIG_PATH:-/etc/cloudflared/config.yml}"

START_HML_MYSQL="${START_HML_MYSQL:-0}"
HML_MYSQL_PORT="${HML_MYSQL_PORT:-3307}"
HML_MYSQL_ROOT_PASSWORD="${HML_MYSQL_ROOT_PASSWORD:-rootpassword}"

main "$@"

