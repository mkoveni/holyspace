.PHONY: up down build logs shell-php setup

# Start all services
up:
	docker compose up -d

# Stop all services
down:
	docker compose down

# Build containers
build:
	docker compose build

# View logs
logs:
	docker compose logs -f

# Shell into PHP container
shell-php:
	docker compose exec php bash
shell-db:
	docker compose exec database bash
dump-autoload:
	docker exec -i church-php composer dump-autoload -o

# Full setup: build, start, install deps, migrate, seed
setup: build up
	@echo "⏳ Waiting for containers to start..."
	@echo ""
	@echo "✅ Church portal is ready!"
	@echo "🔌 Backend:      http://localhost:8081"