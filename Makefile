all: doc test

test: test-example
	./vendor/bin/phpunit -c phpunit.xml --testdox

test-coverage: test-example
	./vendor/bin/phpunit -c phpunit.xml --testdox --coverage-html test/output/coverage
	find test/output/coverage/ -type f -name '*.html' -print0 | xargs -0 sed -i 's!$(PWD)!libsmalldb: !g'

benchmark: test-example
	./vendor/bin/phpunit -c phpunit.xml --testdox --testsuite benchmark

test-example:
	$(MAKE) -C ./test/example.json --no-print-dir

./vendor/bin/phpunit:
	composer install --dev

doc:
	make -C doc/


.PHONY: all test test-coverage test-example doc benchmark

