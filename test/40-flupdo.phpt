--TEST--
Flupdo examples, using SQLite
--FILE--
<?php

require(dirname(__FILE__).'/init.php');

echo "Creating training dummy ...\n";
$db_filename = dirname(__FILE__).'/training_dummy.sdb';
$db = new SQLite3($db_filename);
$db->exec('CREATE TABLE numbers (n INT)');
$db->close();
echo "\n";

echo "Initializing Flupdo on training dummy ...\n";
$flupdo = new \Smalldb\Flupdo\Flupdo('sqlite:'.$db_filename);
echo "\n";

/*
echo "Simple inserts:\n";
$flupdo->beginTransaction();
for ($i = 1; $i <= (1 << 8); $i += $i) {
	$q = $flupdo->insert()->into('numbers')->values(array('n' => $i));
	echo (string) $q, "\n";
	$r = $q->query();
	if ($r->errorCode() != 0) {
		print_r($r->errorInfo());
	}
}
$flupdo->commit();
echo "\n";
*/

echo "Simple select:\n";
$q = $flupdo->select('n AS TheNumber')
	->select('n + 1')
	->distinct()
	->select('n + 2')
	->headerComment('Simple select')
	->from('numbers')
	->where('n > 5')
	->where('n < 100')
	->orderBy('n DESC');
echo "\n", $q, "\n";
$r = $q->query();
if (!$r) {
	echo "Error: ", join(' ', $flupdo->errorInfo()), "\n";
} else {
	print_table($r->fetchAll(PDO::FETCH_ASSOC));
}
echo "\n";

echo "Sub-select:\n";
$q = $flupdo->select('n')
	->from('numbers')
	->where(array('n > ', $flupdo->select('MIN(n)')->from('numbers')))
	->where('n < ?', 100)
	->orderBy('n DESC');
echo "\n", $q, "\n";
$r = $q->query();
if (!$r) {
	echo "Error info: ", join(' ', $flupdo->errorInfo()), "\n";
} else {
	print_table($r->fetchAll(PDO::FETCH_ASSOC));
}
echo "\n";


?>
--CLEAN--
<?php

$db_filename = dirname(__FILE__).'/training_dummy.sdb';
if (file_exists($db_filename)) {
	unlink($db_filename);
}

?>
--EXPECT--

