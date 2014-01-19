all: doc

.PHONY: all clean doc

doc: figures
	../../../core/bin/build-plugin-documentation.sh

figures:
	make -C figures all

clean:
	rm -rf doxygen/

