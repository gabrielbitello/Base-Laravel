.PHONY: help up up-tools down build restart logs shell mysql fresh seed setup migrate tinker cache test assets

DOCKER_COMPOSE = docker compose -p base-laravel -f docker/docker-compose.yml

help: ## Lista de comandos
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Sobe os containers
	$(DOCKER_COMPOSE) up -d

up-tools: ## Sobe os containers + phpMyAdmin
	$(DOCKER_COMPOSE) --profile tools up -d

down: ## Para todos os containers
	$(DOCKER_COMPOSE) --profile tools down

build: ## Builda as imagens
	$(DOCKER_COMPOSE) build --no-cache

restart: ## Reinicia os containers
	$(DOCKER_COMPOSE) restart

logs: ## Mostra os logs
	$(DOCKER_COMPOSE) logs -f

shell: ## Abre bash no container serve
	$(DOCKER_COMPOSE) exec serve bash

setup: ## Setup inicial (composer, key, migrate, npm build)
	$(DOCKER_COMPOSE) exec serve composer install
	$(DOCKER_COMPOSE) exec serve php artisan key:generate
	$(DOCKER_COMPOSE) exec serve php artisan migrate --force
	$(DOCKER_COMPOSE) exec serve sh -c "npm install && npm run build"

migrate: ## Roda as migrations
	$(DOCKER_COMPOSE) exec serve php artisan migrate

fresh: ## migrate:fresh + seed
	$(DOCKER_COMPOSE) exec serve php artisan migrate:fresh --seed

seed: ## Roda o seeder
	$(DOCKER_COMPOSE) exec serve php artisan db:seed

tinker: ## Abre o Tinker
	$(DOCKER_COMPOSE) exec serve php artisan tinker

cache: ## Limpa caches do Laravel
	$(DOCKER_COMPOSE) exec serve php artisan optimize:clear

test: ## Roda os testes
	$(DOCKER_COMPOSE) exec serve php artisan test

assets: ## Build dos assets (produção)
	$(DOCKER_COMPOSE) exec serve sh -c "npm install && npm run build"

mysql: ## Acessa o MySQL via CLI
	$(DOCKER_COMPOSE) exec mysql mysql -u$${DB_USERNAME:-base-laravel} -p$${DB_PASSWORD:-password} $${DB_DATABASE:-base-laravel}
