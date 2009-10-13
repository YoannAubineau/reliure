<?php

define(RELIURE_BASEPATH, dirname(__FILE__));

// function is_direct_request() {
//     $request_dir = dirname($_SERVER['REQUEST_URI'].'.');
//     $script_dir = dirname($_SERVER['SCRIPT_NAME'].'.');
//     return ($request_dir == $script_dir);
// }
// 
// if (is_direct_request()) {
//     header('Location: doc/');
//     exit();
// }

define(INCLUDE_DIRPATH, RELIURE_BASEPATH.'/src');

require_once(INCLUDE_DIRPATH.'/index.php');

?>