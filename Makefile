.PHONY: dev stop cache-clear install db-migrate db-reset test worker check-deps screenshots

check-deps:
	@echo "Checking dependencies..."
	@command -v php >/dev/null 2>&1 || { echo "PHP is not installed. Install with: brew install php"; exit 1; }
	@command -v composer >/dev/null 2>&1 || { echo "Composer is not installed. Install with: brew install composer"; exit 1; }
	@command -v symfony >/dev/null 2>&1 || { echo "Symfony CLI is not installed. Install with: brew install symfony-cli/tap/symfony-cli"; exit 1; }
	@command -v chromedriver >/dev/null 2>&1 || { echo "chromedriver is not installed. Install with: brew install chromedriver"; exit 1; }
	@echo "All dependencies installed."

install:
	composer install

dev:
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

db-reset:
	rm -f var/data/users.db var/data/subscriptions.db var/data/content.db
	$(MAKE) db-migrate
	php bin/console cache:pool:clear cache.app

test:
	php bin/phpunit

worker:
	php bin/console messenger:consume scheduler_default --time-limit=$${WORKER_TTL:-55}

screenshots: check-deps
	@echo "Stopping any running services..."
	-pkill -f chromedriver 2>/dev/null || true
	-symfony server:stop 2>/dev/null || true
	@echo "Resetting test database..."
	rm -f var/data/test_users.db var/data/test_subscriptions.db var/data/test_content.db
	APP_ENV=test php bin/console doctrine:migrations:migrate --em=users --no-interaction
	APP_ENV=test php bin/console doctrine:migrations:migrate --em=subscriptions --no-interaction
	APP_ENV=test php bin/console doctrine:migrations:migrate --em=content --no-interaction
	@echo "Starting ChromeDriver..."
	chromedriver --port=9515 & CHROME_PID=$$!; \
	sleep 2; \
	echo "Starting Symfony server..."; \
	APP_ENV=test symfony server:start --port=8001 --daemon --no-tls; \
	sleep 2; \
	echo "Capturing screenshots..."; \
	APP_ENV=test php bin/console app:capture-screenshots --base-url=http://127.0.0.1:8001; \
	echo "Stopping Symfony server..."; \
	symfony server:stop; \
	echo "Stopping ChromeDriver..."; \
	kill $$CHROME_PID 2>/dev/null || true; \
	echo "Done!"

serve-prod:
	APP_ENV=prod APP_DEBUG=0 php bin/console doctrine:migrations:migrate --em=users --no-interaction
	APP_ENV=prod APP_DEBUG=0 php bin/console doctrine:migrations:migrate --em=subscriptions --no-interaction
	APP_ENV=prod APP_DEBUG=0 php bin/console doctrine:migrations:migrate --em=content --no-interaction
	APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
	APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup
	APP_ENV=prod APP_DEBUG=0 php bin/console messenger:consume scheduler_default & \
	WORKER_PID=$$!; \
	trap "kill $$WORKER_PID 2>/dev/null" EXIT; \
	APP_ENV=prod APP_DEBUG=0 symfony serve --no-tls
