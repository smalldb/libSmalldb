all: doc

.PHONY: all clean doc schema

doc: figures schema
	cd .. && ./vendor/smalldb/doc-tools/bin/build-documentation

figures:
	make -C figures all

clean:
	rm -rf doxygen/


schema_src = $(wildcard ../class/*.schema.json ../class/Auth/*.schema.json)
schema_json = $(patsubst ../class/%.schema.json,doxygen/html/%.schema.json,$(schema_src))
schema_html = $(patsubst ../class/%.schema.json,doxygen/html/%.schema.html,$(schema_src))

schema: $(schema_json) $(schema_html)

doxygen/html/%.schema.json: ../class/%.schema.json Makefile ../class/JsonSchemaReader.php
	mkdir -p $(dir $@)
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaReader::loadFile($$argv[1]);' $< > $@

doxygen/html%.schema.html: ../class/%.schema.json Makefile ../class/JsonSchemaHtmlRenderer.php ../class/JsonSchemaReader.php
	mkdir -p $(dir $@)
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaHtmlRenderer::loadFile($$argv[1]);' $< > $@

