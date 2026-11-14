.PHONY: migrate build up down install test stan cs deptrac check console shell db-reset

DC = docker compose
RUN = $(DC) run --rm --no-deps app

build:
	$(DC) build

up:
	$(DC) up -d database mailpit app

down:
	$(DC) down

migrate:
	$(DC) run --rm app php bin/console app:migrate

install:
	$(RUN) composer install

console:
	$(DC) run --rm app php bin/console $(ARGS)

shell:
	$(DC) run --rm app bash

# Parallel: every worker has its own database (onechurch_symfony_test_1, _2, ...). TEST_PROCESSES sets the worker count.
test:
	$(DC) run --rm -e TEST_PROCESSES app composer test

# One process, one database: for debugging a single test or an order-dependent failure.
test-serial:
	$(DC) run --rm app vendor/bin/phpunit $(ARGS)

stan:
	$(RUN) vendor/bin/phpstan analyse --memory-limit=1G

cs:
	$(RUN) vendor/bin/php-cs-fixer fix

deptrac:
	$(RUN) vendor/bin/deptrac analyse --no-progress

check: cs stan deptrac test
