.PHONY: serve stop cache-clear install db-migrate db-reset test

install:
	composer install

serve:
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
