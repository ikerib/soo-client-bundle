.PHONY: help install test stan all

help:
	@echo ""
	@echo "pasaia/sso-client-bundle — comandos disponibles"
	@echo "================================================="
	@echo "  make install   Instala dependencias Composer"
	@echo "  make test      Ejecuta tests PHPUnit"
	@echo "  make stan      Ejecuta PHPStan nivel 8"
	@echo "  make all       install + test + stan"
	@echo ""

install:
	composer install --no-interaction

test:
	vendor/bin/phpunit --testdox

stan:
	vendor/bin/phpstan analyse --level=8 src tests

all: install test stan
