
all: doc test

test:
	make -C ./test/example --no-print-dir
	echo ; pear run-tests ./test ; echo

doc:
	make -C doc/


.PHONY: all test doc

