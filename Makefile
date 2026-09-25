.PHONY: help up up-tools down build restart ps stats logs shell mysql fresh seed setup migrate tinker cache test lint analyse assets dev prod prod-down prod-logs update update-prod

DOCKER_COMPOSE = docker compose -p base-laravel -f docker/docker-compose.yml
DOCKER_COMPOSE_PROD = docker compose -p base-laravel-prod --project-directory . -f docker/production-compose.yml
PROD_IMAGE = base-laravel:local

help: ## Lista de comandos
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Sobe os containers (dev)
	@if [ -n "$$($(DOCKER_COMPOSE_PROD) ps -q serve)" ]; then \
		echo "O stack de produção está rodando e usa as mesmas portas (80/443/3306). Rode 'make prod-down' antes de 'make up'."; exit 1; fi
	$(DOCKER_COMPOSE) up -d

up-tools: ## Sobe os containers + phpMyAdmin
	$(DOCKER_COMPOSE) --profile tools up -d

down: ## Para todos os containers
	$(DOCKER_COMPOSE) --profile tools down

build: ## Builda as imagens
	$(DOCKER_COMPOSE) build --no-cache

restart: ## Reinicia os containers
	$(DOCKER_COMPOSE) restart

ps: ## Lista os containers (dev e produção local)
	@echo "== Dev =="; $(DOCKER_COMPOSE) ps
	@echo "== Produção local =="; $(DOCKER_COMPOSE_PROD) ps

stats: ## CPU/RAM dos containers (dev e produção local)
	@dev_ids=$$($(DOCKER_COMPOSE) ps -q); \
	prod_ids=$$($(DOCKER_COMPOSE_PROD) ps -q); \
	ids="$$dev_ids $$prod_ids"; \
	if [ -n "$$ids" ]; then docker stats $$ids; \
	else echo "Nenhum container rodando. Use: make up ou make prod"; fi

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

lint: ## Corrige o estilo do código (Pint)
	vendor/bin/pint --dirty

analyse: ## Análise estática (Larastan)
	vendor/bin/phpstan analyse

assets: ## Build dos assets (produção)
	$(DOCKER_COMPOSE) exec serve sh -c "npm install && npm run build"

dev: ## Roda serve+queue+logs+vite dentro do container (artisan dev)
	$(DOCKER_COMPOSE) exec serve php artisan dev

prod: ## Modo produção no local: builda a imagem e sobe serve+worker+scheduler+mysql
	@if [ -n "$$($(DOCKER_COMPOSE) ps -q serve)" ]; then \
		echo "O stack dev está rodando e usa as mesmas portas (80/3306). Rode 'make down' antes de 'make prod'."; exit 1; fi
	docker build -f docker/Production.Dockerfile -t $(PROD_IMAGE) .
	APP_NAME=base-laravel-prod DOCKER_IMAGE=$(PROD_IMAGE) $(DOCKER_COMPOSE_PROD) up -d --wait mysql serve
	$(DOCKER_COMPOSE_PROD) exec -T serve php artisan migrate --force
	APP_NAME=base-laravel-prod DOCKER_IMAGE=$(PROD_IMAGE) $(DOCKER_COMPOSE_PROD) up -d --wait --remove-orphans
	@echo "Stack de produção no ar. Acesso: http://localhost (ou a porta de APP_PORT no .env)"

prod-down: ## Para o stack de produção local
	$(DOCKER_COMPOSE_PROD) down

prod-logs: ## Logs do stack de produção local
	$(DOCKER_COMPOSE_PROD) logs -f

update: ## Atualiza o que estiver rodando, sem rebuild: dev (composer+migrate) e/ou produção (sync+composer+migrate+optimize)
	@dev_up=$$($(DOCKER_COMPOSE) ps -q serve); prod_up=$$($(DOCKER_COMPOSE_PROD) ps -q serve); \
	if [ -z "$$dev_up" ] && [ -z "$$prod_up" ]; then \
		echo "Nenhum stack rodando. Use: make up (dev) ou make prod (produção)"; exit 1; fi; \
	if [ -n "$$dev_up" ]; then \
		echo "== Dev =="; \
		$(DOCKER_COMPOSE) exec -T serve sh -c 'composer install --no-interaction --optimize-autoloader && php artisan migrate --force && php artisan optimize:clear -q' \
		|| exit 1; \
	fi; \
	if [ -n "$$prod_up" ]; then \
		echo "== Produção =="; \
		$(MAKE) --no-print-directory update-prod || exit 1; \
	else \
		echo "Produção não está rodando (make prod para sincronizá-la)."; \
	fi

update-prod:
	@image_date=$$(docker image inspect $(PROD_IMAGE) --format '{{.Created}}' 2>/dev/null); \
	if [ -n "$$image_date" ] && [ "$$(date -d "$$image_date" +%s)" -lt "$$(date -r docker/Production.Dockerfile +%s)" ]; then \
		echo "AVISO: Production.Dockerfile mudou depois do último build da imagem. Instalações de pacotes (ex.: Java/extensões) exigem 'make prod' — o update só sincroniza código."; fi
	npm run build
	@for c in serve worker scheduler; do \
		echo ">> Sincronizando arquivos em $$c..."; \
		tar -C . --exclude='bootstrap/cache' --exclude='public/hot' -cf - \
			app bootstrap config database public resources routes artisan composer.json composer.lock \
			| $(DOCKER_COMPOSE_PROD) exec -T $$c sh -c 'tar -xf - -C /var/www'; \
		echo ">> composer install em $$c..."; \
		$(DOCKER_COMPOSE_PROD) exec -T $$c sh -c 'composer install --no-dev --no-interaction --optimize-autoloader'; \
	done
	$(DOCKER_COMPOSE_PROD) exec -T serve sh -c 'php artisan migrate --force && php artisan optimize -q && php artisan filament:optimize -q && php artisan queue:restart'
	$(DOCKER_COMPOSE_PROD) restart serve
	@code=000; for i in $$(seq 1 30); do code=$$(curl -s -o /dev/null -w '%{http_code}' http://localhost/up 2>/dev/null); [ "$$code" = "200" ] && break; sleep 1; done; \
	echo "Update aplicado. /up: $$code"

mysql: ## Acessa o MySQL via CLI
	$(DOCKER_COMPOSE) exec mysql mysql -u$${DB_USERNAME:-base-laravel} -p$${DB_PASSWORD:-password} $${DB_DATABASE:-base-laravel}

translate: ## Adiciona tradução: make translate KEY="English key" PT="texto" [UPDATE=1]
	@KEY="$(KEY)" PT="$(PT)" UPDATE="$(UPDATE)" node scripts/add-translation.mjs

missing-translations: ## Lista chaves __() usadas nas views sem tradução
	@php scripts/find-missing-translations.php

update-template: ## Puxa atualizações do template base (configure: git config template.remote <url>)
	@remote=$$(git config template.remote); \
	if [ -z "$$remote" ]; then \
		echo "Configure o remoto do template primeiro:"; \
		echo "  git config template.remote <url-do-Base-Laravel>"; exit 1; fi; \
	git remote | grep -qx template || git remote add template "$$remote"; \
	git fetch template main && git merge template/main --no-edit --allow-unrelated-histories
