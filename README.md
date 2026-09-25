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

## 🛠️ Comandos Disponíveis

```bash
make up         # Sobe os containers
make up-tools   # Sobe os containers + phpMyAdmin
make down       # Para todos os containers
make build      # Rebuild das imagens
make restart    # Reinicia os containers
make shell      # Acessa o container
make logs       # Mostra logs

make setup      # Setup inicial (composer, key, migrate, npm)
make migrate    # Roda migrations
make fresh      # migrate:fresh + seed
make seed       # Roda seeders
make tinker     # Abre o Tinker
make cache      # Limpa caches

make test       # Roda testes
make assets     # Build dos assets
make mysql      # Acessa MySQL CLI
```

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
