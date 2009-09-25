<?php

require_once('string.php');  # lowercase, uppercase


$xpath_translations = array(
    # eg. //foo[lower-case(@id)="bar"]
    array('/lower-case\((.*?)\)/', 
          'translate(${1}, "'.$uppercase.'", "'.$lowercase.'")',
    ),
    # eg. //foo[upper-case(@id)="BAR"]
    array('/upper-case\((.*?)\)/', 
          'translate(${1}, "'.$lowercase.'", "'.$uppercase.'")',
    ),
    # eg. //foo[@class~="bar"]
    # This operator is borrowed from CSS 2.1 selectors. It is _not_ part of
    # XPath specification. It however comes very handy when dealing with class 
    # attributes, which can then be interpreted as space-sperated token lists 
    # wherease dumb strings.
    # The previous example means : every foo elements whose class attribute
    # contains a space-separated token "bar".
    array('/([^\s\[\(]+)~=[\'"]?([^\s\]\'"]+)[\'"]?/',
          'contains(concat(" ", ${1}, " "), " ${2} ")',
    ),
);


function preprocess_xpath_query($query) {
    global $xpath_translations;
    
    foreach ($xpath_translations as $translation) {
        list($pattern, $replacement) = $translation;
        $query = preg_replace($pattern, $replacement, $query);
    }
    return $query;
}


function xpath(/* $refnode, $query[, $query, ...] */) {
    $args = func_get_args();
    $refnode = array_shift($args);
    $doc = $refnode->ownerDocument;
    if (is_a($refnode, 'DOMDocument')) {
        $doc = $refnode;
        $refnode = $doc->documentElement;
    }
    $queries = $args;
        
    $nodes = array();
    $xpath = new DOMXPath($doc);
    foreach ($queries as $query) {
        $query = preprocess_xpath_query($query);
        $result = $xpath->query($query, $refnode);
        foreach ($result as $node) {
            $nodes[] = $node;
        }
    }
    return $nodes;
}


function xpath_first_node(/* $refnode, $query[, $query, ...] */) {
    $args = func_get_args();
    $nodes = call_user_func_array(xpath, $args);
    $first_node = $nodes[0];
    return $first_node;
}

?>