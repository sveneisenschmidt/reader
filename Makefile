.PHONY: serve stop cache-clear install db-migrate db-reset test worker

install:
	composer install

serve:
	php bin/console messenger:consume scheduler_default & \
	WORKER_PID=$$!; \
	trap "kill $$WORKER_PID 2>/dev/null" EXIT; \
	symfony serve --no-tls

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
