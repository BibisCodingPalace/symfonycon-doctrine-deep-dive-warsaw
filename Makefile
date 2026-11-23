DOCKER_COMPOSE ?= docker compose
EXEC_APP       = $(DOCKER_COMPOSE) exec app
PHP            = $(EXEC_APP) php
CONSOLE        = $(PHP) bin/console
COMPOSER       = $(EXEC_APP) composer

.DEFAULT_GOAL := help

.PHONY: help
help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "\033[1;32m%-22s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: build up install migrate fixtures ## Build, start and initialise everything in one go

.PHONY: build
build: ## Build the application image
	$(DOCKER_COMPOSE) build

.PHONY: up
up: ## Start the stack in the background
	$(DOCKER_COMPOSE) up -d

.PHONY: down
down: ## Stop and remove the stack
	$(DOCKER_COMPOSE) down --remove-orphans

.PHONY: restart
restart: down up ## Restart the stack

.PHONY: logs
logs: ## Follow container logs
	$(DOCKER_COMPOSE) logs -f

.PHONY: shell
shell: ## Open a shell inside the app container
	$(EXEC_APP) bash

.PHONY: install
install: ## Install PHP dependencies inside the container
	$(COMPOSER) install

.PHONY: migrate
migrate: ## Run Doctrine migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

.PHONY: fixtures
fixtures: ## Load Doctrine fixtures (purges the database and the result cache)
	$(CONSOLE) doctrine:fixtures:load --no-interaction
	$(CONSOLE) cache:pool:clear doctrine.result_cache_pool

.PHONY: cleanup-tests
cleanup-tests: ## Drop the test database
	$(CONSOLE) doctrine:database:drop --env=test --if-exists --force

.PHONY: prepare-tests
prepare-tests: ## Create and migrate the test database
	$(CONSOLE) doctrine:database:create --env=test --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration

.PHONY: run-all-tests
run-all-tests: ## Run the full PHPUnit test suite
	$(PHP) bin/phpunit --testdox

.PHONY: run-stateless-tests
run-stateless-tests: ## Run only the tests which do not write to the database
	$(PHP) bin/phpunit --testdox --exclude-group stateful

.PHONY: test
test: cleanup-tests prepare-tests run-all-tests ## Recreate the test database and run the full test suite

.PHONY: compile-assets
compile-assets: ## Compile the asset map for prod
	$(CONSOLE) asset-map:compile --env=prod
