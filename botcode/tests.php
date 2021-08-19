<?php 
// The tests will be done with a different dbsuffix so will not affect production data

require('config.php');
require('functions.php');

$db = new mysqli(str_replace('p:', '', $db_host), $db_user, $db_pass, $db_name);
if ($db->connect_error) {
	logm('db conn err');
	die('Database connection error.');
}

?>
	<style>
		body {
			font-family: sans;
		}
		pre {
			display: inline;
		}
		:not(pre) {
			font-size: 11pt;
		}
	</style>
	<pre>
<?php 

$starttime = microtime(true); // after db connect

$fakeSendMsg = true;
$dbsuffix = '_test';
$log = '>stdout';
$testcount = 0;

$db->query("TRUNCATE TABLE paytrack$dbsuffix") or die('Database error 21184: ' . $db->error);
$db->query("TRUNCATE TABLE paytrack_users$dbsuffix") or die('Database error 20470: ' . $db->error);
$db->query("TRUNCATE TABLE paytrack_connect$dbsuffix") or die('Database error 13428: ' . $db->error);

function fake($user, $msg) {
	global $fakeInput, $webtoken, $dbsuffix, $log, $testcount;
	$testcount++;

	echo "<br><br>$user[fname]: $msg<br>";

	$fname = $user['fname'];
	$lname = $user['lname'];
	$uid = $user['uid'];
	$chatid = $user['chatid'];

	$fakeInput = json_encode([
		'message' => [
			'date' => time(),
			'from' => [
				'first_name' => $fname,
				'last_name'  => $lname,
				'id'         => $uid,
			],
			'chat' => [
				'id' => $chatid,
			],
			'text' => $msg,
		]
	]);
	$_GET['webtoken'] = $webtoken;
	return include('update.php');
}

$luc = ['fname' => 'Luc', 'lname' => '', 'uid' => 1, 'chatid' => 11];
$fred = ['fname' => 'Frederic', 'lname' => '', 'uid' => 2, 'chatid' => 12];
$sally = ['fname' => 'Sally', 'lname' => 'Salty', 'uid' => 3, 'chatid' => 13];
$joe = ['fname' => 'Joe', 'lname' => 'Smith', 'uid' => 4, 'chatid' => 14];
$jane = ['fname' => 'Jane', 'lname' => 'Smith', 'uid' => 5, 'chatid' => 15];

// Basic tests
fake($luc, '/start');
fake($luc, '/unknowncommand');
fake($luc, '/unknown command');
fake($luc, '/help');
fake($luc, "sally");
fake($luc, "sally 5: pizza");
fake($luc, "sally 5: pizza");
fake($luc, "sally -10");
fake($luc, "sally");
fake($luc, "mrs white");
fake($luc, "mrs white -20: invoice to be paid");
fake($luc, "mrs white 20");
fake($luc, "mrs white 0");
fake($luc, "mrs white");
fake($luc, "newname 0");
fake($luc, "newname -0.0");
fake($luc, "/total");
fake($luc, "/totalall");

echo "<br><br>-------- Single connection tests<br>";
fake($luc, '/connect ');
$code = fake($luc, '/connect sally');
fake($sally, "/start $code");
fake($sally, "luc 20");
fake($luc, "sally 10");

echo "<br><br>-------- Mutual tests";

$code = fake($luc, '/connect fred');
fake($luc, "/start $code"); // should fail (cannot connect to yourself)
fake($fred, "/start invalidlink"); // should fail (missing "start" and "last" identifiers)
fake($fred, "/start s-invalidcode-l"); // should fail (nonsense code)
fake($fred, "/start " . str_replace('2l', '3l', $code)); // should fail (security code does not match id 3)
fake($fred, "/start $code");
fake($fred, "/start $code"); // should fail (use same link twice)
$code = fake($fred, "/connect luc");
fake($luc, "/start $code");

fake($luc, "fred -5");
// Check Fred's balance for Luc (should have been updated)
$balance = $db->query("SELECT amount FROM paytrack$dbsuffix WHERE uid = 2 AND name = 'luc'")->fetch_row()[0];
if ($balance != 5) {
	echo "FAIL: Fred's balance for Luc was not updated when Luc updated their balance for Fred. Expected 5 got $balance<br>";
}
else {
	echo "Ok: Fred's balance for Luc was updated when Luc updated their balance for Fred.<br>";
}

fake($fred, "luc -5");
// Check Fred's balance for Luc (should have been updated)
$balance = $db->query("SELECT amount FROM paytrack$dbsuffix WHERE uid = 1 AND name = 'fred'")->fetch_row()[0];
if ($balance != 0) {
	echo "FAIL: Luc's balance for Fred was not updated when Fred updated their balance for Luc. Expected 0 got $balance.<br>";
}
else {
	echo "Ok: Luc's balance for Fred was updated when Fred updated their balance for Luc.<br>";
}

echo "<br><br>-------- Reminders";

fake($luc, '/remind nonexistent');
fake($luc, 'fred');
fake($luc, '/remind fred');
fake($luc, '/remind fred: just saying :)');
fake($luc, '/remind mrs white');
fake($luc, 'sally');
fake($luc, '/remind sally');

echo "<br><br>-------- Syncup when connecting mutually";

fake($sally, 'luc');
fake($luc, 'sally');

$code = fake($sally, '/connect luc');
fake($luc, "/start $code");

fake($sally, 'luc');
fake($luc, 'sally');

// The amount between 'luc' and 'my boss' should be preserved when connecting because it's equal (both sides think 'my boss' owes 'luc' 1500)
// This also tests having multiple open invite codes
$code  = fake($joe, '/connect luc');
$code2 = fake($joe, '/connect girlfriend');
$code3 = fake($luc, '/connect my boss');
fake($joe, '/rename nobody: nothing');
fake($joe, '/rename girlfriend: wife');
fake($joe, 'luc -1500');
fake($luc, 'my boss 1500');
fake($luc, "/start $code");
fake($jane, "/start $code2");
fake($joe, "/start $code3");
fake($joe, 'luc');
fake($luc, 'my boss');
fake($jane, 'sweetie 200: new couch');
$code = fake($jane, '/connect sweetie');
fake($joe, "/start $code");
fake($joe, 'girlfriend');
fake($joe, 'wife');
fake($jane, 'sweetie');
fake($joe, 'wife 200');
fake($jane, 'sweetie');

echo "<br><br>-------- Prevent duplicate connections";

$code = fake($luc, '/connect fakefred');
fake($fred, "/start $code");

fake($luc, 'fakefred 1');
fake($luc, 'fred 2');
fake($fred, 'luc 4');

echo "<br><br>-------- Disconnecting";

fake($luc, '/disconnect fred');
fake($luc, '/disconnect fred');
fake($luc, 'fred 8');
fake($fred, 'luc 16');
fake($luc, 'fred');
fake($fred, 'luc');
fake($fred, '/disconnect luc');
fake($fred, 'luc 32');

echo '<br><br><br>';

$tt = round((microtime(true) - $starttime) * 1000) / 1000; // time taken
$testpersec = round($testcount / $tt * 10) / 10;
echo "Faked $testcount messages in $tt seconds ($testpersec messages per second); ";
$stats = $db->get_connection_stats();
$queries = $stats['result_set_queries'] + $stats['non_result_set_queries'];
$qps = round($queries / $tt * 10) / 10;
$qpm = round($queries / $testcount * 10) / 10;
$up = $stats['bytes_sent'] / 1024;
$down = $stats['bytes_received'] / 1024;
$upq = round($up / $queries * 1024);
$dpq = round($down / $queries * 1024);
$up = round($up * 10) / 10;
$down = round($down * 10) / 10;
echo "<br>$queries database queries ($qpm queries/message; $qps queries/second); $up/$down KB up/down database traffic ($upq/$dpq B per query)<br>";

echo '</pre>' . str_repeat('<br>', 50);

