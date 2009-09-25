<?php

$start = microtime();

require_once(INCLUDE_DIRPATH.'/reliure.php');

$stop = microtime();
$elapsed = ($stop - $start) * 1000;

$msg = PRODUCT_NAME.' served by '.$_SERVER['SERVER_ADDR'].' in '.$elapsed.'ms.';
echo '<!-- '.$msg.' -->'."\n";

?>