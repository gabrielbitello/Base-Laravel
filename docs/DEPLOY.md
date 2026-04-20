# Deploy — Guia de Configuração

## Pré-requisitos

- VPS com Ubuntu 22.04+ (ou similar)
- Acesso root/sudo
- Domínio apontando para o IP da VPS (opcional, mas recomendado)

---

## 1. Preparar a VPS

### Instalar Docker

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
```

Deslogar e logar novamente para aplicar o grupo.

### Criar usuário de deploy

```bash
sudo adduser deploy --disabled-password
sudo usermod -aG docker deploy
```

### Configurar SSH Key

Na sua máquina local, gere uma chave (ou use uma existente):

```bash
ssh-keygen -t ed25519 -C "deploy-key" -f ~/.ssh/deploy_key
```

Copie a chave pública para a VPS:

```bash
ssh-copy-id -i ~/.ssh/deploy_key.pub deploy@SEU_IP
```

Teste o acesso:

```bash
ssh -i ~/.ssh/deploy_key deploy@SEU_IP
```

### Criar estrutura de pastas

```bash
# Produção
sudo mkdir -p /opt/app
sudo chown deploy:deploy /opt/app

# Previews (opcional)
sudo mkdir -p /opt/previews
sudo chown deploy:deploy /opt/previews
```

### Copiar arquivos necessários para produção

```bash
# Na VPS, dentro de /opt/app:
mkdir -p docker

# Copie production-compose.yml e preview-compose.yml
# O .env de produção é gerado automaticamente pelo workflow
```

### Subir o MySQL compartilhado de HML

Um único MySQL serve todos os previews. Cada branch cria um **database isolado** (não um container separado).

```bash
cd /opt/app

# Copie o hml-mysql-compose.yml para a VPS
# Configure a senha root
export HML_MYSQL_ROOT_PASSWORD=sua-senha-root-hml

docker compose -f docker/hml-mysql-compose.yml up -d
```

> O MySQL compartilhado roda na porta `3307` por padrão (para não conflitar com o MySQL de produção). Os previews se conectam a ele via rede Docker `hml-shared`.

### Firewall (UFW)

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

---

## 2. Configurar GitHub Secrets

No repositório GitHub: **Settings → Secrets and variables → Actions**

### Secrets por Environment

Os secrets ficam **apenas para produção**. O ambiente de staging/preview usa o arquivo `.env.staging` commitado no repositório.

#### Secrets do environment `production`

| Secret | Descrição | Exemplo |
|---|---|---|
| `VPS_HOST` | IP ou domínio da VPS | `123.45.67.89` |
| `VPS_USER` | Usuário SSH | `deploy` |
| `VPS_SSH_KEY` | Chave privada SSH | (conteúdo) |
| `APP_PATH` | Pasta da aplicação prod | `/opt/app` |
| `APP_NAME` | Nome da aplicação | `Minha App` |
| `APP_KEY` | Chave Laravel | `base64:...` |
| `APP_URL` | URL pública | `https://app.com` |
| `APP_PORT` | Porta HTTP | `80` |
| `DB_DATABASE` | Nome do banco | `app` |
| `DB_USERNAME` | Usuário do banco | `app` |
| `DB_PASSWORD` | Senha do banco | `senha-prod-forte` |
| `DB_ROOT_PASSWORD` | Senha root MySQL | `root-prod-forte` |
| `MAIL_MAILER` | Driver de email | `smtp` |
| `MAIL_HOST` | Host SMTP | `smtp.mailgun.org` |
| `MAIL_PORT` | Porta SMTP | `587` |
| `MAIL_USERNAME` | Usuário SMTP | `user@mail.com` |
| `MAIL_PASSWORD` | Senha SMTP | `senha-smtp` |
| `MAIL_FROM_ADDRESS` | Email remetente | `noreply@app.com` |
| `MAIL_FROM_NAME` | Nome remetente | `Minha App` |

#### Secrets compartilhados (sem environment — usados em hml e cleanup)

| Secret | Descrição | Exemplo |
|---|---|---|
| `VPS_HOST` | IP ou domínio da VPS | `123.45.67.89` |
| `VPS_USER` | Usuário SSH | `deploy` |
| `VPS_SSH_KEY` | Chave privada SSH | (conteúdo) |
| `HML_PATH` | Pasta base para previews | `/opt/previews` |
| `PROD_PATH` | Pasta prod (para copiar compose) | `/opt/app` |
| `HML_MYSQL_PORT` | Porta do MySQL compartilhado de HML | `3307` |
| `HML_MYSQL_ROOT_PASSWORD` | Senha root do MySQL de HML | `root-hml` |
| `PREVIEW_DOMAIN` | Domínio base para previews | `preview.seudominio.com` |
| `CLOUDFLARE_TUNNEL_CONFIG_PATH` | Caminho do config.yml do tunnel na VPS | `/etc/cloudflared/config.yml` |
| `CLOUDFLARE_TUNNEL_ID` | ID do tunnel (UUID) | `a1b2c3d4-...` |
| `CLOUDFLARE_ZONE_ID` | Zone ID do domínio no Cloudflare | `abc123...` |
| `CLOUDFLARE_API_TOKEN` | Token com permissão DNS:Edit | `cf-token-...` |

#### Arquivo `.env.staging` (commitado no repositório)

O arquivo `.env.staging` na raiz do projeto contém **todas as variáveis para o ambiente de homologação**. Como HML não tem dados sensíveis reais (usa `MAIL_MAILER=log`, banco local com senha genérica), é seguro commitar.

Para alterar configuração do HML, basta editar `.env.staging` e fazer push — o próximo deploy aplicará automaticamente.

### Environments no GitHub

Crie dois environments em **Settings → Environments**:

- **production** — associe a branch `main`
- **preview** — sem restrição de branch

---

## 3. Arquivo `.env` de Produção

> **O `.env` é gerado automaticamente pelo workflow de deploy** a partir dos GitHub Secrets. Não é necessário criar ou manter um `.env` manualmente na VPS.
>
> A cada deploy, o workflow sobrescreve o `.env` com os valores atuais dos secrets. Para alterar qualquer variável, atualize o secret no GitHub e faça um novo deploy.

---

## 4. Configurar Cloudflare Tunnel (para preview environments)

O Cloudflare Tunnel permite expor os previews com URLs públicas e HTTPS, sem abrir portas no firewall.

### Instalar cloudflared na VPS

```bash
curl -fsSL https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb -o cloudflared.deb
sudo dpkg -i cloudflared.deb && rm cloudflared.deb
```

### Autenticar e criar o tunnel

```bash
cloudflared tunnel login
cloudflared tunnel create preview-tunnel
```

Anote o **Tunnel ID** (UUID retornado) — será o secret `CLOUDFLARE_TUNNEL_ID`.

### Criar config inicial

```bash
sudo mkdir -p /etc/cloudflared
sudo tee /etc/cloudflared/config.yml <<'EOF'
tunnel: SEU_TUNNEL_ID
credentials-file: /root/.cloudflared/SEU_TUNNEL_ID.json

ingress:
  - service: http_status:404
EOF
```

> A última entrada (`http_status:404`) é o **catch-all obrigatório**. Os workflows inserem rotas dinâmicas antes dela.

### Instalar como serviço

```bash
sudo cloudflared service install
sudo systemctl enable cloudflared
sudo systemctl start cloudflared
```

### Permissões para o usuário deploy

O workflow executa `sudo systemctl restart cloudflared`. Adicione ao sudoers:

```bash
echo "deploy ALL=(ALL) NOPASSWD: /bin/systemctl restart cloudflared" | sudo tee /etc/sudoers.d/cloudflared
```

### Obter credenciais Cloudflare para os secrets

| O que | Onde encontrar |
|---|---|
| `CLOUDFLARE_TUNNEL_ID` | Output de `cloudflared tunnel create` ou `cloudflared tunnel list` |
| `CLOUDFLARE_ZONE_ID` | Dashboard Cloudflare → seu domínio → Overview → Zone ID (sidebar direita) |
| `CLOUDFLARE_API_TOKEN` | Dashboard → My Profile → API Tokens → Create Token → **Edit zone DNS** |
| `CLOUDFLARE_TUNNEL_CONFIG_PATH` | `/etc/cloudflared/config.yml` (padrão) |
| `PREVIEW_DOMAIN` | O domínio base que você quer usar (ex: `preview.meuapp.com`) |

### DNS wildcard (opcional mas recomendado)

Em vez de criar um CNAME por branch, crie um wildcard:

```
*.preview.seudominio.com → CNAME → SEU_TUNNEL_ID.cfargotunnel.com (proxied)
```

Se fizer isso, pode remover o step "Create Cloudflare DNS record" do workflow (e o "Remove" do cleanup) — todas as branches resolverão automaticamente.

---

## 5. Login no Container Registry (na VPS)

O deploy puxa imagens do GitHub Container Registry. Autentique:

```bash
# Na VPS, como usuário deploy:
echo "SEU_GITHUB_TOKEN" | docker login ghcr.io -u SEU_GITHUB_USER --password-stdin
```

Crie um Personal Access Token em https://github.com/settings/tokens com permissão `read:packages`.

---

## 6. Como funciona o Deploy

### Produção (branch `main`)

1. Push na `main` dispara o workflow
2. Imagem é buildada e publicada no `ghcr.io`
3. Conecta via SSH na VPS
4. Faz `docker compose pull` + `up -d` (zero-downtime com `start-first`)
5. Roda `php artisan migrate --force`

### Preview (outras branches)

1. Push em qualquer branch (exceto `main`) dispara o workflow
2. Cria um ambiente isolado em `/opt/previews/{branch-slug}/`
3. Sobe containers com porta aleatória
4. Adiciona rota no Cloudflare Tunnel → `<branch-slug>.preview.seudominio.com`
5. Cria registro CNAME no Cloudflare DNS apontando para o tunnel
6. Ao deletar a branch, o workflow `cleanup` remove containers, rota do tunnel e DNS

**Resultado:** cada branch fica acessível em `https://<slug>.preview.seudominio.com` com HTTPS automático via Cloudflare.

---

## 7. Containers de Produção

O `production-compose.yml` define 3 containers da aplicação:

| Container | Função | Grace Period |
|---|---|---|
| **serve** | FrankenPHP (HTTP) | 30s — requests em andamento terminam |
| **worker** | Queue worker | 90s — job atual termina antes de parar |
| **scheduler** | Schedule:work | 30s — task atual termina |

O comportamento é controlado pela variável `CONTAINER_ROLE` no `entrypoint.sh`.

---

## 8. Troubleshooting

### Ver logs

```bash
cd /opt/app
docker compose -f docker/production-compose.yml logs -f serve
docker compose -f docker/production-compose.yml logs -f worker
```

### Restart manual

```bash
docker compose -f docker/production-compose.yml restart serve
```

### Acessar o container

```bash
docker compose -f docker/production-compose.yml exec serve bash
```

### Rollback

```bash
# Use a tag do commit anterior
docker compose -f docker/production-compose.yml pull
# Ou altere DOCKER_IMAGE no .env para a tag específica
docker compose -f docker/production-compose.yml up -d
```

