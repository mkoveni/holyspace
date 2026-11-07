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

test:
	$(DC) run --rm app vendor/bin/phpunit

stan:
	$(RUN) vendor/bin/phpstan analyse --memory-limit=1G

cs:
	$(RUN) vendor/bin/php-cs-fixer fix

deptrac:
	$(RUN) vendor/bin/deptrac analyse --no-progress

check: cs stan deptrac test
