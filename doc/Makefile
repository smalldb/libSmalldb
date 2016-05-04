all: doc

.PHONY: all clean doc

doc: figures
	# ../../../../core/bin/build-plugin-documentation.sh
	cd .. && ./vendor/cascade/doc-tools/bin/build-documentation

figures:
	make -C figures all

clean:
	rm -rf doxygen/

