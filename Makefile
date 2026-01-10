.PHONY: dev dev-with-worker stop cache-clear install db-migrate db-reset db-create-dev-user test coverage check-deps screenshots

check-deps:
	@echo "Checking dependencies..."
	@command -v php >/dev/null 2>&1 || { echo "PHP is not installed. Install with: brew install php"; exit 1; }
	@command -v composer >/dev/null 2>&1 || { echo "Composer is not installed. Install with: brew install composer"; exit 1; }
	@command -v symfony >/dev/null 2>&1 || { echo "Symfony CLI is not installed. Install with: brew install symfony-cli/tap/symfony-cli"; exit 1; }
	@command -v chromedriver >/dev/null 2>&1 || { echo "chromedriver is not installed. Install with: brew install chromedriver"; exit 1; }
	@echo "All dependencies installed."

install:
	composer install

dev: stop cache-clear
	symfony serve --no-tls --allow-all-ip

dev-with-worker:
	php bin/console messenger:consume scheduler_default & \
	WORKER_PID=$$!; \
	trap "kill $$WORKER_PID 2>/dev/null" EXIT; \
	symfony serve --no-tls --allow-all-ip

stop:
	symfony server:stop

cache-clear:
	php bin/console cache:pool:clear cache.app
	php bin/console cache:clear

db-migrate:
	php bin/console doctrine:migrations:migrate --em=users --no-interaction
	php bin/console doctrine:migrations:migrate --em=subscriptions --no-interaction
	php bin/console doctrine:migrations:migrate --em=content --no-interaction
	php bin/console doctrine:migrations:migrate --em=messages --no-interaction

db-reset:
	rm -f var/data/*_users.db var/data/*_subscriptions.db var/data/*_content.db var/data/*_messages.db
	$(MAKE) db-migrate
	php bin/console cache:pool:clear cache.app

test:
	php bin/phpunit

db-create-dev-user:
	@if [ "$$APP_ENV" = "test" ] || [ "$$APP_ENV" = "prod" ]; then \
		echo "Error: db-create-dev-user can only run in dev environment"; \
		exit 1; \
	fi
	sqlite3 var/data/dev_users.db < fixtures/dev-user.sql
	@echo "Dev user created: dev@localhost.arpa / devdevdev"

screenshots: check-deps
	@echo "Stopping any running services..."
	-pkill -f chromedriver 2>/dev/null || true
	-symfony server:stop 2>/dev/null || true
	@echo "Resetting dev database..."
	rm -f var/data/dev_users.db var/data/dev_subscriptions.db var/data/dev_content.db var/data/dev_messages.db
	APP_ENV=dev php bin/console doctrine:migrations:migrate --em=users --no-interaction
	APP_ENV=dev php bin/console doctrine:migrations:migrate --em=subscriptions --no-interaction
	APP_ENV=dev php bin/console doctrine:migrations:migrate --em=content --no-interaction
	APP_ENV=dev php bin/console doctrine:migrations:migrate --em=messages --no-interaction
	@echo "Starting ChromeDriver..."
	chromedriver --port=9515 & CHROME_PID=$$!; \
	sleep 2; \
	echo "Starting Symfony server..."; \
	APP_ENV=dev symfony server:start --port=8001 --daemon --no-tls; \
	sleep 2; \
	echo "Capturing screenshots..."; \
	APP_ENV=dev php bin/console reader:capture-screenshots --base-url=http://127.0.0.1:8001; \
	echo "Stopping Symfony server..."; \
	symfony server:stop; \
	echo "Stopping ChromeDriver..."; \
	kill $$CHROME_PID 2>/dev/null || true; \
	echo "Done!"
