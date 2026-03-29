.PHONY: help install test test-e2e test-unit proto-generate proto-clean build up down logs clean

# Default target
help:
	@echo "TiKV PHP Client - Available commands:"
	@echo ""
	@echo "  make install          - Install PHP dependencies"
	@echo "  make test             - Run all tests (unit + e2e)"
	@echo "  make test-unit        - Run unit tests only"
	@echo "  make test-e2e         - Run E2E tests with TiKV cluster"
	@echo "  make proto-generate   - Generate PHP classes from proto files"
	@echo "  make proto-clean      - Remove generated proto classes"
	@echo "  make build            - Build Docker images"
	@echo "  make up               - Start TiKV cluster"
	@echo "  make down             - Stop TiKV cluster"
	@echo "  make logs             - Show TiKV cluster logs"
	@echo "  make clean            - Clean everything (containers + volumes)"
	@echo "  make example          - Run basic example"
	@echo ""

# Install dependencies
install:
	@echo "Installing PHP dependencies..."
	docker-compose run --rm php-client composer install

# Run all tests
test: test-unit test-e2e
	@echo "All tests completed!"

# Run unit tests only (no TiKV required)
test-unit:
	@echo "Running unit tests..."
	docker-compose run --rm php-client vendor/bin/phpunit --testsuite Unit --testdox

# Run E2E tests with TiKV cluster
test-e2e:
	@echo "Running E2E tests..."
	./scripts/test-e2e.sh

# Generate PHP classes from proto files with CrazyGoat\TiKV\Proto namespace
proto-generate:
	@echo "Generating PHP classes from proto files..."
	@echo "Creating dummy proto files for dependencies..."
	@touch proto/kvproto/proto/rustproto.proto
	@touch proto/kvproto/proto/encryptionpb.proto
	@echo "Generating classes with namespace CrazyGoat\\TiKV\\Proto..."
	docker-compose run --rm php-client sh -c "cd /app/proto/kvproto/proto && \
		for f in *.proto; do \
			echo 'option php_namespace = \"CrazyGoat\\\\TiKV\\\\Proto\";' >> \$$f; \
		done && \
		protoc --php_out=/app/src/Proto *.proto"
	@echo "Proto classes generated with namespace CrazyGoat\\TiKV\\Proto!"
	@echo ""
	@echo "Note: If you see errors about missing dependencies, run: make proto-clean && make proto-generate"

# Clean generated proto classes
proto-clean:
	@echo "Cleaning generated proto classes..."
	@rm -rf src/Proto/*
	@mkdir -p src/Proto
	@echo "Proto classes cleaned!"

# Build Docker images
build:
	@echo "Building Docker images..."
	docker-compose build

# Start TiKV cluster
up:
	@echo "Starting TiKV cluster..."
	docker-compose up -d pd tikv1 tikv2 tikv3
	@echo "Waiting for cluster to be healthy..."
	@sleep 10
	@docker-compose ps

# Stop TiKV cluster
down:
	@echo "Stopping TiKV cluster..."
	docker-compose down

# Show logs
logs:
	@echo "Showing TiKV cluster logs..."
	docker-compose logs -f

# Clean everything
clean:
	@echo "Cleaning everything..."
	docker-compose down -v --remove-orphans
	@rm -rf vendor/
	@rm -f composer.lock
	@echo "Clean complete!"

# Run basic example
example:
	@echo "Running basic example..."
	docker-compose run --rm php-client php examples/basic.php

# Development shell
shell:
	@echo "Opening development shell..."
	docker-compose run --rm php-client sh
