all: doc test

test: test-example
	./vendor/bin/phpunit -c phpunit.xml --testdox

test-coverage: test-example
	./vendor/bin/phpunit -c phpunit.xml --testdox --coverage-html test/coverage
	find test/coverage/ -type f -name '*.html' -print0 | xargs -0 sed -i 's!$(PWD)/!libsmalldb: !g'

test-example:
	$(MAKE) -C ./test/example --no-print-dir

./vendor/bin/phpunit:
	composer install --dev

doc:
	make -C doc/


.PHONY: all test test-coverage test-example doc

