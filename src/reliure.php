<?php

define(BASEURL, "");

if (ENGINE_RUNNING != "TRUE") {
    ob_start();
    define(BASEPATH, dirname(__FILE__));
    define(ENGINE_RUNNING, "TRUE");
}
else {
    
    $content = ob_get_contents();
    ob_end_clean();

    $url = explode("/", $_GET["url"]);
    $reversed_url = array_reverse($url);
    $page_name = $reversed_url[1];

    $doc = new DOMDocument();
    $doc->loadXML($content);
    $doc->head = $doc->getElementsByTagName("head")->item(0);
    $doc->body = $doc->getElementsByTagName("body")->item(0);

    function removeElement($element) {
        $element->parentNode->removeChild($element);
        return $element;
    }

    function addElementClass($element, $class_name) {
        $class_names = $element->getAttribute("class");
        $class_names = trim("$class_names $class_name");
        $element->setAttribute("class", $class_names);
    }

    function hasElementClass($element, $class_name) {
        $class_names = explode(" ", $element->getAttribute("class"));
        return in_array($class_name, $class_names);
    }

    function getElementsByTagAndClassName($tag_name=NULL, $class_name=NULL, $parent=NULL) {
        global $doc;
        $parent = $parent ? $parent : $doc;
        $tag_name = $tag_name ? $tag_name : "*";
        $elements = $parent->getElementsByTagName($tag_name);
        $selected_elements = array();
        foreach($elements as $element) {
            if($class_name == NULL || hasElementClass($element, $class_name)) {
                $selected_elements[] = $element;
            }
        }
        return $selected_elements;
    }

    function getFirstElementByTagAndClassName($tag_name=NULL, $class_name=NULL, $parent=NULL) {
        $elements = getElementsByTagAndClassName($tag_name, $class_name, $parent);
        return $elements[0];
    }

    function setDocumentSubtitle($msg) {
        $title = getFirstElementByTagAndClassName("title");
        if (strpos($title->nodeValue, "|") === FALSE) {
            $title->nodeValue .= " | " . $msg;
        }
    }

    addElementClass($doc->body, $page_name);

    function followRedirect($ul) {
        $li = getFirstElementByTagAndClassName("li", NULL, $ul);
        $link = getFirstElementByTagAndClassName("a", NULL, $li);
        $url = $link->getAttribute("href");
        header("Location: $url");
        exit(0);
    }

    // Forme les liens des menus de navigation et positionne les classes
    //
    $nav_elements = getElementsByTagAndClassName(NULL, "nav");
    foreach($nav_elements as $nav) {
        $map = array();
        $li_elements = getElementsByTagAndClassName("li", NULL, $nav);
        foreach(array_reverse($li_elements) as $li) {
            $link = getFirstElementByTagAndClassName("a", NULL, $li);
            $name = $link->getAttribute("href");
            addElementClass($li, $name);
            if ($name == $page_name) {
                $ul = getFirstElementByTagAndClassName("ul", NULL, $li);
                if ($ul != NULL && hasElementClass($ul, "gotofirst")) {
                    followRedirect($ul);
                }
                addElementClass($li, "selected");
                setDocumentSubtitle($link->nodeValue);
            }
            $baseurl = array($name);
            $parent = $li->parentNode;
            while(!$parent->isSameNode($nav)) {
                if ($parent->tagName == "li") {
                    $parent_link = getFirstElementByTagAndClassName("a", NULL, $parent);
                    $baseurl[] = $parent_link->getAttribute("href");
                    if ($name == $page_name) {
                        addElementClass($parent, "selected_parent");
                    }
                }
                $parent = $parent->parentNode;
            }
            $url = BASEURL ."/". implode(array_reverse($baseurl), "/") . "/";
            $link->setAttribute("href", $url);
        }
        if ($page_name == "") {
            $ul = getFirstElementByTagAndClassName("ul", NULL, $nav);
            if ($ul != NULL && hasElementClass($ul, "gotofirst")) {
                followRedirect($ul);
            }
        }
    }

    // Supprime les frames hors contexte
    //
    $frames = getElementsByTagAndClassName(NULL, "frame");
    foreach(array_reverse($frames) as $frame) {
        if(!hasElementClass($frame, $page_name)) {
            removeElement($frame);
        }
    }

    echo $doc->saveXML();
}

?>