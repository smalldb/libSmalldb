all: doc test

test: test-example
	./vendor/bin/phpunit -c phpunit.xml

test-coverage: test-example
	./vendor/bin/phpunit -c phpunit.xml --coverage-html test/coverage

test-example:
	$(MAKE) -C ./test/example --no-print-dir

./vendor/bin/phpunit:
	composer install --dev

doc:
	make -C doc/


.PHONY: all test test-coverage test-example doc

