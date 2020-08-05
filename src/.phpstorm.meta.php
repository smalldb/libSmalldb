<?php

namespace PHPSTORM_META {

	override(\Smalldb\StateMachine\Definition\ExtensibleDefinition::getExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));
	override(\Smalldb\StateMachine\Definition\StateMachineDefinition::getExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));
	override(\Smalldb\StateMachine\Definition\StateDefinition::getExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));
	override(\Smalldb\StateMachine\Definition\ActionDefinition::getExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));
	override(\Smalldb\StateMachine\Definition\TransitionDefinition::getExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));
	override(\Smalldb\StateMachine\Definition\PropertyDefinition::getExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));

	override(\Smalldb\StateMachine\Definition\ExtensibleDefinition::findExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));
	override(\Smalldb\StateMachine\Definition\StateMachineDefinition::findExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));
	override(\Smalldb\StateMachine\Definition\StateDefinition::findExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));
	override(\Smalldb\StateMachine\Definition\ActionDefinition::findExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));
	override(\Smalldb\StateMachine\Definition\TransitionDefinition::findExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));
	override(\Smalldb\StateMachine\Definition\PropertyDefinition::findExtension(0), map(['@&\Smalldb\StateMachine\Definition\ExtensionInterface']));

	override(\Smalldb\StateMachine\Definition\Builder\ExtensiblePlaceholder::getExtensionPlaceholder(0), map(['@&\Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface']));
	override(\Smalldb\StateMachine\Definition\Builder\StateMachinePlaceholder::getExtensionPlaceholder(0), map(['@&\Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface']));
	override(\Smalldb\StateMachine\Definition\Builder\TransitionPlaceholder::getExtensionPlaceholder(0), map(['@&\Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface']));
	override(\Smalldb\StateMachine\Definition\Builder\ActionPlaceholder::getExtensionPlaceholder(0), map(['@&\Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface']));
	override(\Smalldb\StateMachine\Definition\Builder\StatePlaceholder::getExtensionPlaceholder(0), map(['@&\Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface']));
	override(\Smalldb\StateMachine\Definition\Builder\PropertyPlaceholder::getExtensionPlaceholder(0), map(['@&\Smalldb\StateMachine\Definition\Builder\ExtensionPlaceholderInterface']));

}
