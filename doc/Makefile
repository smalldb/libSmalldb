all: doc

.PHONY: all clean doc

doc:
	cd .. \
		&& ./vendor/smalldb/doc-tools/bin/collect-namespaces \
		&& ./vendor/smalldb/doc-tools/bin/build-documentation

clean:
	rm -rf doxygen/


