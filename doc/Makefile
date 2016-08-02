all: doc

.PHONY: all clean doc schema

doc: figures schema
	cd .. && ./vendor/smalldb/doc-tools/bin/build-documentation

figures:
	make -C figures all

clean:
	rm -rf doxygen/


schema_src = $(wildcard ../class/*.schema.json)
schema_json = $(patsubst ../class/%.schema.json,doxygen/html/%.schema.json,$(wildcard ../class/*.schema.json))
schema_html = $(patsubst ../class/%.schema.json,doxygen/html/%.schema.html,$(wildcard ../class/*.schema.json))

schema: $(schema_json) $(schema_html)

doxygen/html/%.schema.json: ../class/%.schema.json Makefile
	mkdir -p doxygen/html
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaReader::loadFile($$argv[1]);' $< > $@

doxygen/html%.schema.html: ../class/%.schema.json Makefile
	mkdir -p doxygen/html
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaHtmlRenderer::loadFile($$argv[1]);' $< > $@

