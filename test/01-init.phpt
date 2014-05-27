--TEST--
State machine initialization - Hello world
--FILE--
<?php

require(dirname(__FILE__).'/init.php');

echo "Initialize backend ...\n";
$smalldb = new Smalldb\StateMachine\SimpleBackend('foo', array(), 'backend');

echo "Register machine type ...\n";
$article_json = json_decode(file_get_contents(dirname(__FILE__).'/example/article.json'), TRUE);
$smalldb->addType('article', '\Smalldb\StateMachine\ArrayMachine', $article_json['state_machine'], array('name' => $article_json['info']['title']));

echo "Known types:\n";
foreach ($smalldb->getKnownTypes() as $t) {
	echo "\t", $t, ":\n";
	foreach ($smalldb->describeType($t) as $k => $v) {
		echo "\t\t", $k, ": ", is_array($v) ? (empty($v) ? '[empty array]' : '[array]') : (is_object($v) ? '{'.get_class($v).'}' : var_export($v)), "\n";
	}
}
echo "\n";

echo "Get null ref ...\n";
$null_ref = $smalldb->nullRef('article');

echo "Available actions for the null ref:\n";
print_r($null_ref->actions);

echo "Create machine instantion - invoke initial transition ...\n";
$ref = $null_ref->create();

echo "Result state: ", var_export($ref->state), "\n";
echo "Result machine type: ", var_export($ref->machineType), "\n";

echo "\n";
echo "Available actions for the new ref:\n";
print_r($ref->actions);

?>
--EXPECT--
Initialize backend ...
Register machine type ...
Known types:
	article:
		name: 'Article in web CMS'
		class: '\\Smalldb\\StateMachine\\ArrayMachine'
		args: [array]

Get null ref ...
Available actions for the null ref:
Array
(
    [0] => create
)
Create machine instantion - invoke initial transition ...
Transition invoked: '' (id = NULL) -> Smalldb\StateMachine\ArrayMachine::create(NULL) [new] -> 'writing' (id = 0).
Result state: 'writing'
Result machine type: 'article'

Available actions for the new ref:
Array
(
    [0] => edit
    [1] => submit
)
