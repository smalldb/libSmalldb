
all: tests
	make -C ./test/example --no-print-directory

tests:
	echo ; pear run-tests ./test ; echo

doc:
	cd doc/ && doxygen


.PHONY: all tests doc

