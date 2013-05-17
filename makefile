
all: tests
	make -C ./test/example --no-print-directory

tests:
	pear run-tests ./test

.PHONY: all tests

