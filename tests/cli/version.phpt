--TEST--
slic help output starts with version 3.0.0
--ENV--
NO_COLOR=1
--FILE--
<?php
echo shell_exec('php ' . escapeshellarg(dirname(__DIR__, 2) . '/slic.php') . ' help 2>&1');
?>
--EXPECTF--
slic version 3.0.0 - StellarWP local testing and development tool%A
