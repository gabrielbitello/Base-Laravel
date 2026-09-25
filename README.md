# Base Laravel

Sistema backend desenvolvido com Laravel 13 e Filament 5.

## 🚀 Requisitos

- Docker e Docker Compose

## 📦 Instalação

```bash
# Clone o repositório
git clone <repo-url>
cd base-laravel

# Suba os containers
make up

# Execute o setup inicial
make setup
```

## 🔐 Credenciais de Desenvolvimento

Ao rodar `make fresh` ou `make seed` em ambiente local, um usuário admin é criado:

| Campo | Valor |
|-------|-------|
| **Email** | `admin@admin.com` |
| **Senha** | `password` |

**Acesso ao painel:** http://localhost/admin

Em ambiente local, a tela de login mostra um botão **Admin (admin@admin.com)** para entrar com um clique (plugin `filament-developer-logins`, desativado fora de local).

O template já vem com RBAC (Shield/spatie permission), auditoria (activity log), mensagens em pt-BR e análise estática no CI — detalhes em [LIBS.md](LIBS.md). Ao iniciar um projeto real, rode `php artisan shield:install admin` para gerar papéis e permissões.

## 🛠️ Comandos Disponíveis

```bash
make up         # Sobe os containers (dev)
make up-tools   # Sobe os containers + phpMyAdmin
make down       # Para todos os containers
make build      # Rebuild das imagens
make restart    # Reinicia os containers
make ps         # Lista os containers (dev e produção local)
make stats      # CPU/RAM dos containers (dev e produção local)
make logs       # Mostra os logs
make shell      # Acessa o container
make dev        # serve+queue+logs+vite dentro do container (artisan dev)

make setup      # Setup inicial (composer, key, migrate, npm)
make migrate    # Roda migrations
make fresh      # migrate:fresh + seed
make seed       # Roda seeders
make tinker     # Abre o Tinker
make cache      # Limpa caches
make lint       # Corrige o estilo do código (Pint)
make analyse    # Análise estática (Larastan)
make test       # Roda testes
make assets     # Build dos assets
make translate KEY="Chave" PT="Texto"  # Adiciona tradução em lang/pt_BR.json
make missing-translations              # Chaves __() usadas sem tradução
make mysql      # Acessa MySQL CLI
```

## 🏭 Modo Produção no Local

Transforma o notebook no host da aplicação usando a mesma imagem e o mesmo stack da VPS (serve + worker + scheduler + MySQL, com opcache e assets buildados):

```bash
make prod        # Builda a imagem local e sobe o stack completo
make update      # Atualiza o que estiver rodando (dev e/ou produção), sem rebuild
make prod-logs   # Logs do stack de produção
make prod-down   # Para o stack
```

O `make prod` roda `migrate --force` no banco do stack. Os stacks dev e produção usam as mesmas portas (80/443/3306) e não sobem juntos — o `make up` e o `make prod` avisam e barram com a instrução de qual `down` usar. As variáveis vêm do `.env` (`APP_PORT`, `DB_*` etc.).

O `make update` é para o dia a dia e age em **qualquer stack que esteja no ar**:
- **Dev** (arquivos já sincronizam pelo volume): `composer install`, `migrate` e limpeza de caches. Sem restart — `queue:listen` e o servidor do dev recarregam sozinhos.
- **Produção**: envia os arquivos alterados para dentro dos containers, `composer install`, `migrate`, regenera os caches (`artisan optimize`), pede reload graceful do worker (`queue:restart`) e reinicia só o serve (~2s, necessário porque o opcache de produção não revalida arquivos).

Mudanças no `Production.Dockerfile` (ex.: instalar Java/extensões) não são sincronizáveis — o update avisa e exige `make prod`, cujo rebuild é incremental (só as camadas que mudaram).

## 🗄️ Banco de Dados

- **Host:** localhost
- **Porta:** 3306
- **Database:** base-laravel
- **Usuário:** base-laravel
- **Senha:** password

**phpMyAdmin:** http://localhost:8080 *(requer `make up-tools`)*

## 📚 Documentação

- [Guia de Bibliotecas](LIBS.md)
- [Guia para Agentes de IA](AGENTS.md)
