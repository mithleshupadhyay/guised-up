SHELL := /usr/bin/env bash
.SHELLFLAGS := -euo pipefail -c

DOCKER_COMPOSE_FILE ?= docker-compose.yml
DOCKER_PROJECT ?= guised-up-assessment
ENV_FILE ?= .env

.PHONY: help ensure-env docker-build docker-up docker-up-build docker-down docker-logs docker-ps docker-exec migrate seed test test-api test-embedding mobile-install mobile-start

help:
	@printf '%s\n' "Available targets:"
	@printf '%s\n' "  make docker-up-build Start Postgres, Laravel API, and embedding service"
	@printf '%s\n' "  make migrate          Run Laravel migrations"
	@printf '%s\n' "  make seed             Seed test users and sample posts"
	@printf '%s\n' "  make test             Run API and embedding service tests"
	@printf '%s\n' "  make mobile-install   Install React Native dependencies"
	@printf '%s\n' "  make mobile-start     Start Expo"

ensure-env:
	@test -f $(ENV_FILE) || (printf '%s\n' "Missing $(ENV_FILE). Run: cp .env.example $(ENV_FILE)" && exit 1)

docker-build: ensure-env
	docker compose --env-file $(ENV_FILE) -f $(DOCKER_COMPOSE_FILE) -p $(DOCKER_PROJECT) build

docker-up: ensure-env
	docker compose --env-file $(ENV_FILE) -f $(DOCKER_COMPOSE_FILE) -p $(DOCKER_PROJECT) up -d

docker-up-build: ensure-env
	docker compose --env-file $(ENV_FILE) -f $(DOCKER_COMPOSE_FILE) -p $(DOCKER_PROJECT) up -d --build

docker-down: ensure-env
	docker compose --env-file $(ENV_FILE) -f $(DOCKER_COMPOSE_FILE) -p $(DOCKER_PROJECT) down -v

docker-logs: ensure-env
	docker compose --env-file $(ENV_FILE) -f $(DOCKER_COMPOSE_FILE) -p $(DOCKER_PROJECT) logs -f --tail=100

docker-ps: ensure-env
	docker compose --env-file $(ENV_FILE) -f $(DOCKER_COMPOSE_FILE) -p $(DOCKER_PROJECT) ps

docker-exec: ensure-env
ifndef SERVICE
	$(error Please provide SERVICE name. Usage: make docker-exec SERVICE=api)
endif
	docker compose --env-file $(ENV_FILE) -f $(DOCKER_COMPOSE_FILE) -p $(DOCKER_PROJECT) exec $(SERVICE) bash

migrate: ensure-env
	docker compose --env-file $(ENV_FILE) -f $(DOCKER_COMPOSE_FILE) -p $(DOCKER_PROJECT) exec api php artisan migrate

seed: ensure-env
	docker compose --env-file $(ENV_FILE) -f $(DOCKER_COMPOSE_FILE) -p $(DOCKER_PROJECT) exec api php artisan db:seed

test: test-api test-embedding

test-api: ensure-env
	docker compose --env-file $(ENV_FILE) -f $(DOCKER_COMPOSE_FILE) -p $(DOCKER_PROJECT) exec api php artisan test

test-embedding:
	cd embedding_service && python -m pytest -q

mobile-install:
	cd mobile && npm install

mobile-start:
	cd mobile && npm run start
