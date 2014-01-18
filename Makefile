
all: doc

tests:
	make -C ./test/example --no-print-dir
	echo ; pear run-tests ./test ; echo

doc:
	make -C doc/


.PHONY: all tests doc

