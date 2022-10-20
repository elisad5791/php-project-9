install:
	composer install

lint:
	composer exec --verbose phpcs -- --standard=PSR12 public

lint-fix:
	composer exec --verbose phpcbf -- --standard=PSR12 public

start:
	php -S localhost:3000 -t public
