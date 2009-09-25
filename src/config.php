<?php

require_once('string.php');  # trim_quotes


function read_config($filename) {
    $config = parse_ini_file($filename);
    $config = array_map(trim_quotes, $config);
    return $config;
}


function load_config($config_or_filename) {
    if (is_string($config_or_filename)) {
        $filename = $config_or_filename;
        $config = read_config($filename);
    }
    else {
        $config = $config_or_filename;
    }
    
    foreach ($config as $key => $value) {
        $key = strtoupper($key);
        define($key, $value);
    }
}

?>
