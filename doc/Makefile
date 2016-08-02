all: doc

.PHONY: all clean doc schema

doc: figures schema
	cd .. && ./vendor/smalldb/doc-tools/bin/build-documentation

figures:
	make -C figures all

schema:
	mkdir -p doxygen/html
	# AbstractMachine
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaHtmlRenderer::loadFile($$argv[1]);' ../class/AbstractMachine.schema.json > doxygen/html/AbstractMachine.schema.html
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaReader::loadFile($$argv[1]);' ../class/AbstractMachine.schema.json > doxygen/html/AbstractMachine.schema.json
	# FlupdoMachine
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaHtmlRenderer::loadFile($$argv[1]);' ../class/FlupdoMachine.schema.json > doxygen/html/FlupdoMachine.schema.html
	php -r 'require "../vendor/autoload.php"; echo Smalldb\StateMachine\JsonSchemaReader::loadFile($$argv[1]);' ../class/FlupdoMachine.schema.json > doxygen/html/FlupdoMachine.schema.json


clean:
	rm -rf doxygen/

