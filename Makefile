all: doc test

test: test-example
	./vendor/bin/phpunit --bootstrap vendor/autoload.php  test

test-coverage: test-example
	./vendor/bin/phpunit --bootstrap vendor/autoload.php --coverage-html test/coverage --whitelist class  test

test-example:
	$(MAKE) -C ./test/example --no-print-dir

./vendor/bin/phpunit:
	composer install --dev

doc:
	make -C doc/


.PHONY: all test test-coverage test-example doc

