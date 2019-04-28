all: doc

.PHONY: all clean doc schema

doc: figures schema
	cd .. && ./vendor/smalldb/doc-tools/bin/build-documentation

figures:
	make -C figures all

clean:
	rm -rf doxygen/


schema_src = $(wildcard ../src/*.schema.json ../src/Auth/*.schema.json)
schema_json = $(patsubst ../src/%.schema.json,doxygen/html/%.schema.json,$(schema_src))
schema_html = $(patsubst ../src/%.schema.json,doxygen/html/%.schema.html,$(schema_src))

schema: $(schema_json) $(schema_html)

doxygen/html/%.schema.json: ../src/%.schema.json Makefile ../src/JsonSchemaReader.php
	mkdir -p $(dir $@)
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaReader::loadFile($$argv[1]);' $< > $@

doxygen/html%.schema.html: ../src/%.schema.json Makefile ../src/JsonSchemaHtmlRenderer.php ../src/JsonSchemaReader.php
	mkdir -p $(dir $@)
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaHtmlRenderer::loadFile($$argv[1]);' $< > $@

