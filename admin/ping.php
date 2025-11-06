<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');
echo "PING OK\n";
echo "__FILE__: " . __FILE__ . "\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Time: " . date('c') . "\n";
