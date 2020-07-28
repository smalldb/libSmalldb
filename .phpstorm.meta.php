<?php

namespace PHPSTORM_META {

	override(
		\Smalldb\StateMachine\Definition\ExtensibleDefinition::getExtension(0),
		map([
			'@' // '@&\Smalldb\StateMachine\Definition\ExtensionInterface',
		])
	);

	override(
		\Smalldb\StateMachine\Definition\Builder\ExtensiblePlaceholder::getExtensionPlaceholder(0),
		map([
			'@' //'@&\Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface',
		])
	);

}
