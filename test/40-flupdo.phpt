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

echo "Simple insert:\n";
$data = array();
for ($i = 1; $i <= (1 << 8); $i += $i) {
	$data[] = array('n' => $i);
}
$flupdo->beginTransaction();
$q = $flupdo->insert('n')->into('numbers')->values($data);
echo $q, "\n";
$r = $q->exec();
if (!$r) {
	echo "Error info: ", join(' ', $flupdo->errorInfo()), "\n\n";
}
$flupdo->commit();
echo "\n";

echo "Simple update:\n";
$q = $flupdo->update('numbers')->where('n > ?', 10)->set('n = n + 1')->limit(2)->where('n < ?', 100);
echo "\n", $q, "\n";
$r = $q->query();
if (!$r) {
	echo "Error: ", join(' ', $flupdo->errorInfo()), "\n\n";
} else {
	echo "Affected rows: ", $r->rowCount(), "\n";
}
echo "\n";

echo "Simple select:\n";
$q = $flupdo->select('n AS TheNumber')
	->select('n + 1')
	->distinct()
	->select('n + 2')
	->headerComment('Simple select')
	->from('numbers')
	->where('n > ?', 5)
	->where('n < ?', 200)
	->orderBy('n DESC');
echo "\n", $q, "\n";
$r = $q->query();
if (!$r) {
	echo "Error: ", join(' ', $flupdo->errorInfo()), "\n\n";
} else {
	print_table($r->fetchAll(PDO::FETCH_ASSOC));
}
echo "\n";

echo "Sub-select:\n";
$q = $flupdo->select('n')
	->from('numbers')
	->where(array('n >', $flupdo->select('MIN(n) + ?', 2)->from('numbers')))
	->where('n < ?', 100)
	->orderBy('n DESC');
echo "\n", $q, "\n";
$r = $q->query();
if (!$r) {
	echo "Error info: ", join(' ', $flupdo->errorInfo()), "\n\n";
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
Creating training dummy ...

Initializing Flupdo on training dummy ...

Simple insert:
	INSERT INTO numbers
		(n)
	VALUES
		(1),
		(2),
		(4),
		(8),
		(16),
		(32),
		(64),
		(128),
		(256)


Simple update:

	UPDATE numbers
	SET n = n + 1
	WHERE (n > ?)
		AND (n < ?)
	LIMIT 2

Affected rows: 2

Simple select:

	-- Simple select
	SELECT DISTINCT n AS TheNumber,
		n + 1,
		n + 2
	FROM numbers
	WHERE (n > ?)
		AND (n < ?)
	ORDER BY n DESC


  +-----------+-------+-------+
  | TheNumber | n + 1 | n + 2 |
  +-----------+-------+-------+
  |     '128' | '129' | '130' |
  |      '64' |  '65' |  '66' |
  |      '33' |  '34' |  '35' |
  |      '17' |  '18' |  '19' |
  |       '8' |   '9' |  '10' |
  +-----------+-------+-------+


Sub-select:

	SELECT n
	FROM numbers
	WHERE (n > (
			SELECT MIN(n) + ?
			FROM numbers
		))
		AND (n < ?)
	ORDER BY n DESC


  +------+
  |  n   |
  +------+
  | '64' |
  | '33' |
  | '17' |
  |  '8' |
  |  '4' |
  +------+
