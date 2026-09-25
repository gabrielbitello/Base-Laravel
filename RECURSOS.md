# 📦 Recursos do Template — Base Laravel

Inventário completo do que este template entrega. Detalhes de uso de cada lib estão em [LIBS.md](LIBS.md); instruções para agentes de IA em [AGENTS.md](AGENTS.md).

---

## 1. Stack base

| Camada | Tecnologia | Versão |
|--------|-----------|--------|
| Framework | Laravel | ^13.17 (13.33) |
| Painel admin | Filament | ^5.8 |
| Linguagem | PHP | ^8.3 (ambientes rodando 8.5) |
| Assets | Vite + laravel-vite-plugin | 8.x / 3.x |
| CSS | Tailwind CSS | 4.x (`@tailwindcss/vite`) |
| Frontend | **TypeScript** strict (`resources/js/*.ts`) | 7.x |
| HTTP client | axios (`resources/js/bootstrap.ts`) | 1.x |
| Banco | MySQL 8.4 (Docker) / SQLite (testes) | — |
| Servidor | FrankenPHP (Caddy + opcache) | imagem 1-php8.5 |

---

## 2. Painel administrativo (Filament)

- **Acesso:** `/admin` (usuário local: `admin@admin.com` / `password` — criado pelo seeder em local)
- Descoberta automática de Resources, Pages e Widgets (`app/Filament/**`)
- Plugins instalados:
  - **Shield** — RBAC com UI (ver §3)
  - **Language Switcher** — troca de idioma por usuário (ver §5)
  - **Developer Logins** — botão de login rápido em local (ver §6)
- **Página de configurações** — `/admin/manage-settings` (ver §7)

---

## 3. RBAC — papéis e permissões

- `spatie/laravel-permission` + **Filament Shield**
- `User` já usa `HasRoles`; `Gate::before` no `AppServiceProvider` dá bypass total ao papel `super_admin`
- Migration da tabela `permissions` publicada
- **Para iniciar um projeto real:** `php artisan shield:install admin`

---

## 4. Auditoria

- `spatie/laravel-activitylog` — tabela `activity`
- `User` já com `LogsActivity` + `CausesActivity` (namespace dos traits: `Spatie\Activitylog\Models\Concerns\` no v5)
- `pxlrbt/filament-activity-log` instalado — para visualizar num resource, crie uma página estendendo `pxlrbt\FilamentActivityLog\Pages\ListActivities`

---

## 5. Internacionalização (i18n)

### Strings de interface (`__()`)

- Locale padrão do template: **pt_BR** (`config/app.php`, `.env.example`, `.env.staging`)
- Dicionário flat: `lang/pt_BR.json` — chave = frase em inglês, editado pelo tooling abaixo
- Mensagens do framework em pt-BR: `lang/pt_BR/*.php` (validation, auth, passwords, pagination — pacote `lucascudo`)

### Tooling de traduções (originado do vip-777)

| Ferramenta | O que faz |
|------------|-----------|
| `make translate KEY="..." PT="..."` | Insere/atualiza chave em ordem alfabética (`UPDATE=1` substitui) |
| `make missing-translations` | Chaves `__()` usadas nas views sem tradução (`--json`, `--verbose`) |
| pre-commit (`.githooks/`) | Valida e normaliza `lang/*.json` staged (JSON válido, chaves ordenadas, 4 espaços) |
| Merge driver `lang-json` | Merge semântico de traduções entre branches, sem conflito |
| CI `auto-fix-lang.yml` | Bot mergeia main no PR, normaliza e empurra de volta |

### Troca de idioma no painel

- `tomatophp/filament-language-switcher` — botão no topbar
- Persistência por usuário: coluna `lang` em `users`, aplicada a cada request
- Locais disponíveis: `config/filament-language-switcher.php` (pt_BR + en)

### Conteúdo de modelos multi-idioma

- `spatie/laravel-translatable` + `lara-zeus/spatie-translatable`
- Trait próprio: `App\Filament\Translatable\Concerns\InteractsWithTranslatableForms`
  - FileUpload translatable (string ↔ array)
  - Reindexa chaves UUID de Repeaters (preserva KeyValue)
  - Fallback de locale **só para escalares** — o fallback recursivo é o bug que corrompeu dados no vippers
- Coberto por `tests/Unit/TranslatableFormsTest.php`; exemplo de uso em [LIBS.md](LIBS.md)

---

## 6. Login rápido (ambiente local)

- `dutchcodingcompany/filament-developer-logins` na tela `/admin/login`
- Ativo **somente** em `local`/`development` — fora disso nem botão nem rota existem
- Usuários oferecidos: `app/Providers/Filament/AdminPanelProvider.php`

---

## 7. Configurações editáveis no painel

- `spatie/laravel-settings` + plugin oficial do Filament
- Exemplo: `app/Settings/SiteSettings.php` (`site_name`) → `/admin/manage-settings`
- Uso no código: `app(App\Settings\SiteSettings::class)->site_name`
- Novos grupos: classe em `app/Settings/` + `make:settings-migration` + página (copiar a ManageSettings)

---

## 8. Make (comandos do dia a dia)

```
make up / up-tools / down / restart / build     # stack dev (Docker)
make ps / stats                                  # containers (dev + produção local)
make dev                                         # serve+queue+logs+vite no container
make setup / migrate / fresh / seed              # banco
make tinker / cache / logs / shell / mysql       # utilitários
make test / lint (Pint) / analyse (Larastan)     # qualidade
make assets                                      # build frontend
make prod / prod-down / prod-logs                # modo produção no local
make update                                      # atualiza o que estiver rodando, sem rebuild
make translate KEY=... PT=...                    # traduções
make missing-translations                        # chaves faltando
```

`composer dev` = `php artisan dev` (serve, queue, Pail, Vite). `composer test` roda testes.

---

## 9. Modo produção no local (notebook hospedando)

- **`make prod`** — builda `base-laravel:local` (composer `--no-dev`, assets, opcache) e sobe o stack completo `serve + worker + scheduler + mysql` com `--wait` e `migrate --force`. Mesma imagem/compose da VPS.
- **`make update`** — dia a dia: sincroniza arquivos nos containers rodando, `composer install`, `migrate`, `artisan optimize`, `queue:restart` (worker graceful) e restart rápido do serve (obrigatório: opcache de produção com `validate_timestamps=0`). No dev, faz composer + migrate + clear.
- Mudanças no `Production.Dockerfile` (pacotes, extensões) exigem `make prod` — o update avisa. O rebuild é incremental.
- Detalhes: `docker/production-compose.yml` (healthchecks reais, startup ordenado), `docker/serve/` (Caddyfile, php.ini de prod, entrypoint por `CONTAINER_ROLE`).

---

## 10. CI/CD (GitHub Actions, gated por `ENABLE_TEMPLATE_WORKFLOWS=true`)

| Workflow | O que faz |
|----------|-----------|
| `quality.yaml` | Pint + Larastan + `composer audit` + build com typecheck + testes (PHP 8.5, Node 22, MySQL 8.4) |
| `auto-fix-lang.yml` | Bot de traduções em PRs (merge + normalização) |
| `deploy-prod.yaml` | Build da imagem → GHCR → deploy na VPS (gera `.env`, `up`, `migrate`) |
| `deploy-hml.yaml` | Preview/HML com `.env.staging` + banco compartilhado |

Deploy invoca compose com `--project-directory .` — corrige a resolução do `env_file` e das variáveis de substituição.

---

## 11. Docker

- **Dev** (`docker/docker-compose.yml`): FrankenPHP com o repo montado, MySQL 8.4, phpMyAdmin (profile `tools`)
- **Produção** (`docker/production-compose.yml` + `Production.Dockerfile`): imagem self-contained, três roles (`serve`/`worker`/`scheduler` via `CONTAINER_ROLE`), healthchecks próprios (worker/scheduler com liveness `kill -0 1` — o healthcheck herdado do FrankenPHP apontava para porta que não existe neles)

---

## 12. Qualidade e segurança

| Ferramenta | Papel |
|-----------|-------|
| Pint | Estilo de código (`make lint`; CI valida) |
| Larastan ^3.12 (`larastan/larastan`) | Análise estática nível 5 (`make analyse`; CI valida) |
| PHPUnit 12 + Paratest | Testes (`artisan test --parallel` disponível) |
| Roave Security Advisories | Bloqueia dependência com CVE no composer |
| `composer audit` | Passo do CI |
| `PreventRequestForgery` | Middleware CSRF (nome do Laravel 13) |
| `serializable_classes => false` + session `serialization: json` | Hardening contra gadget chains (skeleton 13.x) |
| TypeScript strict no build | Erro de tipo quebra build e CI |

---

## 13. DX — ferramentas de desenvolvimento

- **Laravel Debugbar** (local, `APP_DEBUG`) — `barryvdh/laravel-debugbar`
- **Laravel IDE Helper** — `ide-helper:generate` / `ide-helper:models`; artefatos gitignored
- **Laravel Pao** — saída de testes otimizada para agentes de IA
- **Laravel Boost** — MCP server para agentes de IA; configs prontas para Codex/Cursor/VS Code/Zed/`.ai`
- `CLAUDE.md` → symlink para `AGENTS.md` (instruções unificadas)
- Hooks e merge driver registrados automaticamente via `composer install` (post-install-cmd)

---

## 14. Decisões conscientes (o que o template NÃO traz)

- Stripe/Twilio/mapas/spatial/excel/JWT/Socialite/media-library — infra específica de aplicação
- `filament-ptbr-form-fields` — sem release para Filament 5; usar nativo (`TextInput::mask(...)`) — receitas no [LIBS.md](LIBS.md)
- `laravel-lang/common` — exige `ext-bcmath` no host; alternativa atual (`lucascudo`) cobre o essencial

---

## 15. Checklist para derivar um projeto deste template

1. `composer install` (registra hooks + merge driver) e `cp .env.example .env`
2. Ajustar `APP_NAME`/`APP_URL`/`APP_LOCALE` no `.env`
3. `php artisan shield:install admin` (gera papéis/permissões + super admin)
4. Criar models/resources; adicionar campos translatable conforme §5
5. Novos settings: seguir §7
6. Primeiro `make prod`/`make update` local para validar, depois configurar os secrets de deploy
