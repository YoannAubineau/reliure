<?php

require('string.php');  # trim_slashes, unaccentuate


function flatten_array($array) {
    if (! is_array($array)) {
        $value = $array;
        $array = array($value);
    }
    for ($i = 0; $i < count($array); $i++) {
        if (is_array($array[$i])) {
            array_splice($array, $i, 1, $array[$i]);
            $i--;
        }
    }
    return $array;
}


function join_url(/* $url_chunk[, $url_chunk, ...] */) {
    $url_chunks = flatten_array(func_get_args());
    $url_chunks = array_map(trim_slashes, $url_chunks);
    $url = implode($url_chunks, '/');
    return $url;
}


function sort_with_accents($array) {
    $tmp = array();
    foreach ($array as $item) {
        $key = unaccentuate(implode(' ', flatten_array($item)));
        $tmp[$key] = $item;
    }
    ksort($tmp);
    $array = array_values($tmp);
    return $array;
}


function indent_xml_string($string) {
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $doc->loadXML($string);
    $string = $doc->saveXML();
    return $string;
}


function redirect($url) {
    header('Location: '.$url);
    exit();
}

?>
