<?php

require_once('xpath.php'); # xpath, xpath_first_node


function current_document() {
    global $doc;  // XXX get ride of this ugly global import
    return $doc;
}


function create_element($tag_name) {
    $doc = current_document();
    $element = $doc->createElement($tag_name);
    return $element;
}


function create_text_node($text) {
    $doc = current_document();
    $text_node = $doc->createTextNode($text);
    return $text_node;
}


function create_dom(/* $name, [$attrs, [$node[, $node, ...]]] */) {
    $args = func_get_args();
    $name = array_shift($args);
    $attrs = array_shift($args);
    $nodes = $args;
    
    $element = create_element($name);
    if ($attrs)
    foreach ($attrs as $key => $value) {
        $element->setAttribute($key, $value);
    }
    if ($nodes)
    foreach ($nodes as $node) {
        if (is_string($node)) {
            $node = create_text_node($node);
        }
        $element->appendChild($node);
    }
    return $element;
}


function create_dom_func($tag_name) {
    return create_function('', '
        $args = func_get_args();
        array_unshift($args, "'.$tag_name.'");
        return call_user_func_array(create_dom, $args);
    ');
}


$tag_names = array('a', 'button', 'br', 'canvas', 'dd', 'div', 'dl', 'dt', 
    'fieldset', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'img', 
    'input', 'label', 'legend', 'li', 'ol', 'optgroup', 'option', 'p', 'pre', 
    'select', 'span', 'strong', 'table', 'tbody', 'td', 'textarea', 'tfoot', 
    'th', 'thead', 'tr', 'tt', 'ul');

foreach ($tag_names as $tag_name) {
    $tag_names = strtolower($tag_name);
    $func_name = strtoupper($tag_name);
    ${$func_name} = create_dom_func($tag_name);
}


function insert_sibling_node_before($refnode, $node) {
    $refnode->parentNode->insertBefore($node, $refnode);
}


function insert_sibling_node_after($refnode, $node) {
    $refnode->parentNode->insertBefore($node, $refnode->nextSibling);
}


function __cleanup_blanks($string) {
    $string = preg_replace('/^[ \t]+( |\t)/', '', $string);
    $string = preg_replace('/^[ \t\n\r\f\v]+(\n|\r|\f|\v)/', '', $string);
    return $string;
}


function remove_element($node, $cleanup_blanks=False) {
    if ($cleanup_blanks) {
        # delete preceding blank characters
        $prev = $node->previousSibling;
        if ($prev->nodeType == XML_TEXT_NODE) {
            $prev->nodeValue = rtrim($prev->nodeValue);
        }
        # delete following blank characters
        $next = $node->nextSibling;
        if ($next->nodeType == XML_TEXT_NODE) {
            $next->nodeValue = ltrim($next->nodeValue);
        }
    }
    $node->parentNode->removeChild($node);
    return $node;
}


function replace_child_nodes(/* $node, $child_node[, $child_node, ...] */) {
    $args = func_get_args();
    $node = array_shift($args);
    $child_nodes = $args;

    foreach ($node->childNodes as $child_node) {
        remove_element($child_node);
    }
    foreach ($child_nodes as $child_node) {
        $node->appendChild($child_node);
    }
}


function swap_dom(/* $node, $new_node[, $new_node, ...] */) {
    $args = func_get_args();
    $node = array_shift($args);
    $new_nodes = $args;
    
    foreach ($new_nodes as $new_node) {
        insert_sibling_node_before($node, $new_node);
    }
    remove_element($node);
}


function merge_dom($target, $source) {
    if ($source->tagName == $target->tagName) {
        merge_attributes($source, $target);
        swap_dom($target, $source);
    }
    else {
        replace_child_nodes($target, $source);
    }
}


function has_element_class($element, $class_name) {
    $class_names = explode(' ', $element->getAttribute('class'));
    $has = in_array($class_name, $class_names);
    return $has;
}


function add_element_class($element, $class_name) {
    if (has_element_class($element, $class_name)) {
        return;
    }
    $class_names = $element->getAttribute('class');
    $class_names = trim($class_names.' '.$class_name);
    $element->setAttribute('class', $class_names);
}


function merge_attributes($target, $source, $override=False) {
    foreach ($source->attributes as $attr) {
        $current = $target->getAttribute($attr->name);
        if ($override or $current == NULL) {
            $target->setAttribute($attr->name, $attr->value);
        }
        else 
        if ($attr->name == 'class') {
            $class_names = explode(' ', $attr->value);
            foreach ($class_names as $class_name) {
                add_element_class($target, $class_name);
            }
        }
    }
}


function guess_element_indentation($element) {
    # first text node preceding an anscestor
    $text_node = xpath_first_node($element, './ancestor-or-self::*[
        ./preceding-sibling::node()[1]/self::text()][1]/
        ./preceding-sibling::node()[1]/self::text()');
    if (! $text_node) {
        return '';
    }
    $lines = array_reverse(explode("\n", $text_node->nodeValue));
    $indent = $lines[0];
    return $indent;
}


function indent_element($element) {
    $indent = "\n".guess_element_indentation($element);
    if (in_array($element->tagName, array('ul', 'ol'))) {
        insert_sibling_node_before($element, create_text_node($indent));
        $lis = xpath($element, './li');
        foreach ($lis as $li) {
            insert_sibling_node_before($li, create_text_node($indent.INDENT));
            $inner_lists = xpath($li, '(ul|ol)');
            foreach ($inner_lists as $inner_list) {
                indent_element($inner_list);
            }
        }
        $element->appendChild(create_text_node($indent));
    }
    insert_sibling_node_after($element, create_text_node($indent));
}

?>