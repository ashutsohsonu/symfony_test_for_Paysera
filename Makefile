.DEFAULT_GOAL := help

##
## ─── Docker ────────────────────────────────────────────────────────────────────
##

build: ## Build and start all Docker containers
	docker-compose up -d --build

up: ## Start all containers (no rebuild)
	docker-compose up -d

down: ## Stop all containers
	docker-compose down

down-v: ## Stop all containers and delete volumes (wipes DB)
	docker-compose down -v

ps: ## Show container status
	docker-compose ps

logs: ## Tail application logs
	docker-compose exec app tail -f var/log/dev.log

##
## ─── App Setup ─────────────────────────────────────────────────────────────────
##

install: ## Install PHP dependencies
	docker-compose exec app composer install

migrate: ## Run database migrations
	docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

migrate-test: ## Run migrations for test environment
	docker-compose exec app php bin/console doctrine:migrations:migrate --env=test --no-interaction

fixtures: ## Load dev fixtures (seed data)
	docker-compose exec app php bin/console doctrine:fixtures:load --no-interaction

fixtures-test: ## Load test fixtures
	docker-compose exec app php bin/console doctrine:fixtures:load --env=test --no-interaction

setup: build install migrate fixtures ## Full setup: build, install, migrate, seed

##
## ─── Tests ─────────────────────────────────────────────────────────────────────
##

test: migrate-test fixtures-test ## Run all tests (unit + integration)
	docker-compose exec app php bin/phpunit

test-unit: ## Run unit tests only (no DB required)
	docker-compose exec app php bin/phpunit --testsuite=Unit

test-integration: migrate-test fixtures-test ## Run integration tests only
	docker-compose exec app php bin/phpunit --testsuite=Integration

test-coverage: ## Run tests with text coverage report
	docker-compose exec app php bin/phpunit --coverage-text

##
## ─── Code Quality ──────────────────────────────────────────────────────────────
##

cache-clear: ## Clear Symfony cache
	docker-compose exec app php bin/console cache:clear

shell: ## Open a shell inside the app container
	docker-compose exec app sh

##
## ─── Help ──────────────────────────────────────────────────────────────────────
##

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

.PHONY: build up down down-v ps logs install migrate migrate-test fixtures fixtures-test \
        setup test test-unit test-integration test-coverage cache-clear shell help
