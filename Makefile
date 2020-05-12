.DEFAULT_GOAL := help

USER = $(shell id -u):$(shell id -g)

ifdef NO_DOCKER
	PHP = php
	COMPOSER = composer
	NPM = npm
else
	PHP = ./docker/bin/php
	COMPOSER = ./docker/bin/composer
	NPM = ./docker/bin/npm
endif

ifndef COVERAGE
	COVERAGE = --coverage-html ./coverage
endif

ifdef FILE
	PHPUNIT = $(PHP) ./vendor/bin/phpunit --bootstrap ./tests/bootstrap.php --testdox $(FILE)
else
	PHPUNIT = $(PHP) ./vendor/bin/phpunit $(COVERAGE) --whitelist ./src --bootstrap ./tests/bootstrap.php --testdox ./tests
endif

.PHONY: start
start: ## Start a development server (use Docker)
	@echo "Running webserver on http://localhost:8000"
	docker-compose -f docker/docker-compose.yml up

.PHONY: stop
stop: ## Stop and clean Docker server
	docker-compose -f docker/docker-compose.yml down

.PHONY: install
install: ## Install the dependencies
	$(COMPOSER) install
	$(NPM) install

.PHONY: setup
setup: .env ## Setup the application system
	$(PHP) ./cli --request /system/setup

.PHONY: update
update: setup ## Update the application

.PHONY: test
test: ## Run the test suite
	$(PHPUNIT)

.PHONY: lint
lint: ## Run the linters on the PHP and JS files
	$(PHP) ./vendor/bin/phpcs --extensions=php --standard=PSR12 ./src ./tests
	$(NPM) run lint-js
	$(NPM) run lint-css

.PHONY: lint-fix
lint-fix: ## Fix the errors detected by the linters
	$(PHP) ./vendor/bin/phpcbf --extensions=php --standard=PSR12 ./src ./tests
	$(NPM) run lint-js-fix
	$(NPM) run lint-css-fix

.PHONY: help
help:
	@grep -h -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

.env:
	@cp env.sample .env
