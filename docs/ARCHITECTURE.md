# Arquitetura Docker & CI/CD — Explicação Técnica

Este documento explica cada arquivo criado/alterado no sistema de Docker e CI/CD do projeto.

---

## Docker — Desenvolvimento

### `docker/serve/Dockerfile`

**O que faz:** Define a imagem de desenvolvimento local.

**Por que é assim:**
- **FrankenPHP** (`dunglas/frankenphp`) — combina Caddy + PHP em um único processo. Elimina a necessidade de nginx + php-fpm separados, simplificando o setup.
- **install-php-extensions** — script da comunidade que resolve dependências de extensões automaticamente (vs `docker-php-ext-install` que exige instalar libs manualmente).
- **xdebug** — essencial para debugging em dev. Não está no Dockerfile de produção.
- **pcntl** — necessário para graceful shutdown do queue worker e para `php artisan serve` com signals.
- **Node instalado via tarball** — detecta arquitetura (`amd64`/`arm64`) automaticamente, garantindo compatibilidade com Apple Silicon e x86.
- **Composer copiado do image oficial** — garante versão estável sem instalar via curl.

### `docker/serve/Caddyfile`

**O que faz:** Configuração do web server (Caddy/FrankenPHP).

**Por que é assim:**
- `frankenphp` — diretiva global que ativa o módulo PHP do FrankenPHP.
- `order php_server before file_server` — garante que requests PHP são processados antes de tentar servir arquivos estáticos.
- `:80` — escuta apenas HTTP. Em dev não há necessidade de HTTPS (simplifica). Em prod, o Caddy pode ser configurado com auto-TLS.
- `encode gzip` — compressão básica para assets.
- `php_server` — serve PHP automaticamente (equivalente ao `index.php` + `try_files` do nginx).

### `docker/serve/php.ini`

**O que faz:** Configuração PHP para desenvolvimento.

**Por que é assim:**
- `upload_max_filesize = 64M` + `post_max_size = 72M` — `post_max_size` deve ser maior que `upload_max_filesize` pois o POST inclui metadados além do arquivo.
- `memory_limit = 256M` — suficiente para Filament/Livewire sem estourar em dev.
- `max_execution_time = 60` — evita requests travados infinitamente, mas dá tempo para operações pesadas em dev.

### `docker/serve/opcache.ini`

**O que faz:** Configuração do OPcache para desenvolvimento.

**Por que é assim:**
- `validate_timestamps=1` + `revalidate_freq=2` — verifica a cada 2 segundos se o arquivo mudou. Em dev, precisamos que edições sejam refletidas sem restart.
- `memory_consumption=256` — cache generoso para armazenar o bytecode de todos os arquivos do projeto (Laravel + Filament são muitos arquivos).
- `max_accelerated_files=20000` — projetos Laravel/Filament facilmente ultrapassam 10k arquivos PHP.

### `docker/serve/entrypoint.sh`

**O que faz:** Ponto de entrada do container, decide qual role executar.

**Por que é assim:**
- **Multi-role via `CONTAINER_ROLE`** — uma única imagem Docker serve para 3 propósitos (serve, worker, scheduler). Reduz complexidade de build e garante que todos os containers rodam o mesmo código.
- `php artisan optimize` — cacheia config, routes, views. Executado uma vez no boot ao invés de cada request.
- `php artisan filament:optimize` — cacheia componentes Filament (icons, etc).
- `exec` — substitui o processo shell pelo processo final. Garante que signals (SIGTERM) são recebidos diretamente pelo processo PHP/FrankenPHP.

### `docker/docker-compose.yml`

**O que faz:** Compose para desenvolvimento local.

**Alteração feita:** Removida porta 443 (não utilizada — Caddyfile só escuta :80).

**Por que é assim:**
- **`depends_on` com `condition: service_healthy`** — garante que o MySQL está pronto antes de subir a app. Evita erros de conexão no boot.
- **`volumes: ../:/var/www`** — monta o código local no container. Edições refletem instantaneamente sem rebuild.
- **`profiles: [tools]` no phpMyAdmin** — não sobe por padrão (economiza recursos). Ativado com `make up-tools`.
- **Volumes nomeados para MySQL** — persiste dados entre restarts do container.

---

## Docker — Produção

### `docker/Production.Dockerfile`

**O que faz:** Imagem otimizada para produção (código embutido).

**Por que é assim:**
- **Sem xdebug/pcntl** — extensões de debug não devem existir em produção (segurança + performance).
- **`COPY . .`** — código copiado para dentro da imagem. Não depende de volumes externos. Imagem é self-contained e imutável.
- **`composer install --no-dev --optimize-autoloader`** — exclui dependências de dev (phpunit, faker, etc.) e gera classmap otimizado.
- **`npm ci && npm run build && rm -rf node_modules`** — builda assets e remove node_modules (não são necessários em runtime, reduz tamanho da imagem).
- **`prod.php.ini`** — usa configuração agressiva de PHP (opcache sem timestamps).
- **`ENTRYPOINT` ao invés de `CMD`** — o entrypoint executa optimizations antes de qualquer comando. Garante que `optimize` roda mesmo se o CMD for sobrescrito.

### `docker/serve/prod.php.ini`

**O que faz:** Configuração PHP para produção.

**Por que é assim:**
- **`opcache.validate_timestamps=0`** — a diferença mais crítica vs dev. Em produção o código nunca muda em runtime (está na imagem). Desligar a verificação de timestamps elimina syscalls de `stat()` em cada request — ganho significativo de performance.
- **`opcache.save_comments=1`** — necessário para que anotações/attributes PHP funcionem (Filament usa isso).
- Limites de memória e tempo iguais ao dev — podem ser ajustados por projeto.

### `docker/production-compose.yml`

**O que faz:** Compose para produção com 3 containers de aplicação + MySQL.

**Por que é assim:**

#### Container `serve`
- **`deploy.update_config.order: start-first`** — zero-downtime. O novo container sobe e passa no healthcheck ANTES de o antigo ser morto.
- **`healthcheck` em `/up`** — endpoint padrão do Laravel que verifica se a app está funcional.
- **`stop_grace_period: 30s`** — requests HTTP em andamento têm 30s para terminar antes do container ser forçado a parar.

#### Container `worker`
- **`stop_signal: SIGTERM`** — o queue worker do Laravel escuta SIGTERM e para gracefully (termina o job atual, não pega novos).
- **`stop_grace_period: 90s`** — jobs podem ser longos. 90s dá tempo para a maioria terminar. Jobs que excedem são mortos e vão para `failed_jobs` (devem ser retryable).
- **Separado do serve** — se estivesse no mesmo container, matar o serve mataria o worker no meio de um job.

#### Container `scheduler`
- **`schedule:work`** — roda o scheduler em foreground (vs cron). Mais adequado para containers.
- **Separado** — evita que o deploy do serve afete tasks agendadas em execução.

#### MySQL
- **`healthcheck`** — garante que o banco está pronto antes dos containers de app subirem.
- **Volume nomeado** — persiste dados entre deploys.
- **Porta externa configurável** — para acesso remoto se necessário, mas pode ser removida em prod por segurança.

---

## CI/CD — GitHub Actions

### `.github/workflows/quality.yaml`

**O que faz:** Roda em todo push/PR. Verifica code style, segurança e testes.

**Por que é assim:**
- **MySQL como service** — testes rodam com banco real (não SQLite), garantindo que queries e migrations funcionam igual à produção.
- **Pint `--test`** — falha o CI se o código não está formatado. Força o dev a rodar `pint` antes de commitar.
- **`composer audit`** — detecta vulnerabilidades conhecidas em dependências.
- **`npm run build`** — necessário para testes que dependem de assets compilados (Vite manifest).
- **Ordem importa:** Pint e audit rodam antes dos testes — falham rápido e barato.

### `.github/workflows/deploy-prod.yaml`

**O que faz:** Deploy para produção ao pushjar na `main`.

**Por que é assim:**
- **`.env` gerado a partir dos GitHub Secrets** — nenhuma credencial fica na VPS. O workflow gera o `.env` a cada deploy, garantindo que secrets são gerenciados em um único lugar (GitHub) e versionados implicitamente.
- **Job `build` separado do `deploy`** — build é pesado e pode ser cacheado. Deploy é rápido (só pull + up).
- **GHCR (GitHub Container Registry)** — gratuito para repos privados, autenticação nativa com `GITHUB_TOKEN`, sem configuração extra.
- **Tags: SHA + latest** — SHA permite rollback para qualquer commit específico. `latest` é o que o compose puxa por padrão.
- **`migrate --force`** — `--force` é necessário em produção (Laravel pede confirmação sem ele).
- **Verification step** — `php artisan about` confirma que o deploy foi bem-sucedido.
- **Environment `production`** — permite configurar proteções (aprovação manual, etc.) no GitHub.

### `.github/workflows/deploy-hml.yaml`

**O que faz:** Deploy de preview para cada branch.

**Por que é assim:**
- **Usa `.env.staging` commitado no repositório** — ao contrário de produção (que gera o `.env` a partir de GitHub Secrets), o HML usa um arquivo versionado. HML não tem dados sensíveis reais (`MAIL_MAILER=log`, banco local com senha genérica), então é seguro e prático commitar.
- **Slug da branch** — converte `feature/my-feature` em `feature-my-feature` para uso como nome de container, banco, pasta.
- **Pasta isolada por branch** — cada preview tem sua própria pasta, sem interferir nos outros.
- **Valores dinâmicos via `sed -i`** — `APP_URL`, `APP_NAME`, `DB_DATABASE` são substituídos no `.env` para cada branch.
- **Cloudflare Tunnel para URL pública** — ao invés de expor portas ou configurar proxy reverso manualmente, o workflow adiciona uma rota no config do `cloudflared` e cria um CNAME via API. Resultado: `<slug>.preview.seudominio.com` com HTTPS automático.
- **DNS record criado via API** — cada branch ganha um CNAME apontando para o tunnel. Alternativa: usar wildcard DNS e pular este step.
- **`APP_PORT=0`** — Docker atribui porta aleatória. O tunnel roteia pelo hostname para `localhost:<porta>`.
- **Reutiliza `production-compose.yml`** — mesma infra de prod, garantindo que o que funciona em preview funciona em prod.

### `.github/workflows/cleanup.yaml`

**O que faz:** Remove o ambiente de preview quando a branch é deletada.

**Por que é assim:**
- **Trigger `delete`** — GitHub dispara quando a branch é removida (merge + delete, ou delete manual).
- **`down -v`** — remove containers E volumes (banco de dados). Preview é descartável.
- **`rm -rf`** — remove a pasta completamente.
- **Remove rota do Cloudflare Tunnel** — remove as linhas do `config.yml` e reinicia o serviço para liberar o hostname.
- **Remove DNS record via API** — apaga o CNAME do Cloudflare para não acumular registros órfãos.
- **`delete-package-versions`** — limpa imagens antigas do registry para não acumular storage.

---

## Makefile

**Alterações:** Nenhuma — já estava completo para dev.

**Por que existe:** Abstrai comandos longos de Docker Compose em aliases curtos (`make up`, `make shell`, `make fresh`). Melhora DX significativamente.

---

## `.dockerignore`

**O que faz:** Exclui arquivos do contexto de build do Docker.

**Por que é assim:**
- `vendor/` e `node_modules/` — são reinstalados dentro da imagem (garante consistência). Enviar eles no contexto de build seria lento e desnecessário.
- `.git/` — histórico não é necessário na imagem e pode ser enorme.
- `.env` — nunca deve ser copiado para a imagem (secrets). O `.env` de produção é montado via `env_file` no compose.
- `storage/logs/*`, `storage/framework/*` — são runtime files, não devem ir na imagem.

---

## Fluxo Completo (resumo visual)

```
Developer → push branch → quality.yaml (testes)
                         → deploy-hml.yaml (preview)

Developer → merge to main → quality.yaml (testes)
                           → deploy-prod.yaml:
                              1. Build imagem
                              2. Push para ghcr.io
                              3. SSH na VPS
                              4. docker compose pull
                              5. docker compose up (start-first)
                              6. migrate --force
                              7. Container antigo morre (grace period)

Developer → delete branch → cleanup.yaml (remove preview)
```


