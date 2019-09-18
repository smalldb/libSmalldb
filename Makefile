all: doc test

test: test-example ./vendor/bin/phpunit
	./vendor/bin/phpunit -c phpunit.xml --testdox

test-coverage: test-example ./vendor/bin/phpunit
	./vendor/bin/phpunit -c phpunit.xml --testdox --coverage-html test/output/coverage
	find test/output/coverage/ -type f -name '*.html' -print0 | xargs -0 sed -i 's!$(PWD)!libsmalldb: !g'

benchmark: test-example ./vendor/bin/phpunit
	./vendor/bin/phpunit -c phpunit.xml --testdox --testsuite benchmark

test-example:
	$(MAKE) -C ./test/example.json --no-print-dir

./vendor/bin/phpunit:
	composer install --dev

analyze: ./vendor/bin/phpstan
	./vendor/bin/phpstan analyse -l 0 src/ test/[A-Z]*

./vendor/bin/phpstan:
	composer install --dev

doc:
	make -C doc/


.PHONY: all test test-coverage test-example doc benchmark analyze

