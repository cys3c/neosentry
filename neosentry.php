<?php
/*
 * Main core logic to handle elevated and centralized tasks
 */

$o = getopt("d:t:"); // 1 : is required, 2 :: is optional
$device = array_key_exists("d",$o) ? $o["d"] : "";
$type = array_key_exists("t",$o) ? $o["t"] : "";

var_dump($argv);
