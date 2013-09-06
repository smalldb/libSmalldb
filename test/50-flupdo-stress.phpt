--TEST--
Flupdo stress test -- query building only, no DB access
--FILE--
<?php

require(dirname(__FILE__).'/init.php');

echo "Initializing Flupdo on training dummy ...\n";
$flupdo = new \Smalldb\Flupdo\Flupdo('sqlite:/dev/null');
echo "\n";

$start_time = microtime(true);
$mem_last   = memory_get_usage(false);
$loop_count = 1000;
$report_every = ceil($loop_count / 10);

for ($i = 0; $i < $loop_count; $i++) {
	if ($i % $report_every === 0) {
		$m = memory_get_usage();
		printf("Memory usage increase:  %+10.3F KB after %d loops.\n", ($m - $mem_last) / 1024, $i);
		$mem_last = $m;
	}

	$q = (string) $flupdo->select('foo')->from('numbers')->where('n > 10')->where('n < 100')->orderBy('bar ASC, foo DESC')->limit(50);
	unset($q);
}

$end_time = microtime(true);
printf("Test duration:          %10.3F ms, %d times repeated.\n", ($end_time - $start_time) * 1000, $loop_count);

?>
--CLEAN--
<?php

$db_filename = dirname(__FILE__).'/training_dummy.sdb';
if (file_exists($db_filename)) {
	unlink($db_filename);
}

?>
--EXPECTF--
Initializing Flupdo on training dummy ...

Memory usage increase: %s KB after 0 loops.
Memory usage increase: %s KB after %d loops.
Memory usage increase:      +0.000 KB after %d loops.
Memory usage increase:      +0.000 KB after %d loops.
Memory usage increase:      +0.000 KB after %d loops.
Memory usage increase:      +0.000 KB after %d loops.
Memory usage increase:      +0.000 KB after %d loops.
Memory usage increase:      +0.000 KB after %d loops.
Memory usage increase:      +0.000 KB after %d loops.
Memory usage increase:      +0.000 KB after %d loops.
Test duration: %s ms, %d times repeated.

