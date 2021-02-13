<?

require_once 'eu.reader.php';
require_once 'eu.parser.php';
require_once "eu.php";

$eu = new eu;

$_GET['euids'] = $argv[1];
$_GET['headers'] = $argv[2];

// clean up
if($_GET['euids'] === 'undefined') unset($_GET['euids']);
if($_GET['headers'] != 'true') unset($_GET['headers']);

$raw = [];
$_games = explode('|', $_GET['euids']);
foreach($_games as $_id => $_ids) {
	ob_start();
	$eu->game($_ids);
	$raw[] = trim(ob_get_contents());
	ob_end_clean();
}

if(isset($_GET['headers']))
	echo $_GET['headers'] . "\n";

echo preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", implode("\n", $raw));
