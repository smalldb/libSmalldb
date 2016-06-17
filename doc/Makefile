all: doc

.PHONY: all clean doc

doc: figures
	cd .. && ./vendor/smalldb/doc-tools/bin/build-documentation

figures:
	make -C figures all

clean:
	rm -rf doxygen/

