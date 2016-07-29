all: doc

.PHONY: all clean doc schema

doc: figures schema
	cd .. && ./vendor/smalldb/doc-tools/bin/build-documentation

figures:
	make -C figures all

schema:
	mkdir -p doxygen/schema
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaHtmlRenderer::loadFile($$argv[1]);' ../class/AbstractMachine.schema.json > doxygen/schema/AbstractMachine.schema.html


clean:
	rm -rf doxygen/

