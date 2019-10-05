all: doc test

test: test-example ./vendor/bin/phpunit
	./vendor/bin/phpunit -c phpunit.xml --testdox

test-coverage: test-example ./vendor/bin/phpunit
	./vendor/bin/phpunit -c phpunit.xml --testdox --coverage-html test/output/coverage --coverage-php test/output/coverage/coverage.php; ret=$$?; \
	find test/output/coverage/ -type f -name '*.html' -print0 | xargs -0 sed -i 's!$(PWD)!libsmalldb: !g'; \
	cp "test/output/coverage/_js/file.js" "test/output/coverage/_js/file.js~"; \
	sed -i "test/output/coverage/_js/file.js" \
		-e "s/^\\s*\$$('\\.popin')/  \$$('.popin td[data-content]')/" \
		-e "s/\$$\\((this)\\|target\\)\\.children()\\.first()/\$$\\1/g"; \
	exit $$ret

benchmark: test-example ./vendor/bin/phpunit
	./vendor/bin/phpunit -c phpunit.xml --testdox --testsuite benchmark

test-example:
	$(MAKE) -C ./test/example.json --no-print-dir

./vendor/bin/phpunit:
	composer install --dev

analyze: ./vendor/bin/phpstan test/output/covered-files.list
	./vendor/bin/phpstan analyse -l 7 -c phpstan.neon --paths-file=test/output/covered-files.list

test/output/coverage/coverage.php:
	make test-coverage

test/output/covered-files.list: ./test/covered-files.php test/output/coverage/coverage.php
	./test/covered-files.php > test/output/covered-files.list

./vendor/bin/phpstan:
	composer install --dev

doc:
	make -C doc/


.PHONY: all test test-coverage test-example doc benchmark analyze

