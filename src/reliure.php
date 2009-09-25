<?php

require_once(INCLUDE_DIRPATH.'/utils.php');
require_once(INCLUDE_DIRPATH.'/config.php');
require_once(INCLUDE_DIRPATH.'/string.php');
require_once(INCLUDE_DIRPATH.'/dom.php');
require_once(INCLUDE_DIRPATH.'/xpath.php');


/*
 * Read configuration file and set non-overridable constants
 *
*/

define(CONFIG_FILEPATH, ($_GET['config']) ? 
    $_GET['config'] : './config.ini');

$config = read_config(CONFIG_FILEPATH);

$ro_config_keys = array(
    'PRODUCT_NAME', 'PRODUCT_VERSION',
    'URL_QUERYFIELD', 'TEMPLATE_QUERYFIELD', 
    'ROOTPAGE_NAME', 'CHILDPAGE_SUFFIX'
);

foreach ($ro_config_keys as $key) {
    define($key, $config[$key]);
}

define(TEMPLATE_FILEPATH, ($_GET[TEMPLATE_QUERYFIELD]) ? 
    $_GET[TEMPLATE_QUERYFIELD] : $config['DEFAULT_TEMPLATE_FILEPATH']);


/*
 * Read URL and set content selectors
 *
*/

define(URL, ($_GET[URL_QUERYFIELD]) ? 
    $_GET[URL_QUERYFIELD] : $_SERVER['REQUEST_URI']);

$url = explode('/', ltrim(URL, '/'));
define(LANG, strtolower(array_shift($url)));

$reversed_url = array_reverse($url);
define(PAGE_NAME, ($reversed_url[1]) ? $reversed_url[1] : ROOTPAGE_NAME);
define(CHILD_NAME, $reversed_url[0]);
define(FRAME_NAME, (CHILD_NAME) ? PAGE_NAME.CHILDPAGE_SUFFIX : PAGE_NAME);


/*
 * Read template and set BASEURL
 *
*/

# load template WITHOUT executing server-side scripting
$doc = new DOMDocument();
$doc->loadHTMLFile(TEMPLATE_FILEPATH);
$doc->html = $doc->documentElement;

$baseurl = xpath_first_node($doc, 
    '/html/head/base[@href][last()]/@href')->value;
define(BASEURL, rtrim($baseurl, '/'));


/*
 * Reload template with server-side scripting
 *
*/
 
ob_start();
include(TEMPLATE_FILEPATH);
$content = ob_get_clean();

$doc = new DOMDocument();
$doc->loadXML($content);
$doc->html = $doc->documentElement;

# Some settings might have been set previously by the user in the template.
load_config($config);


/*
 * Redirect to default language if none selected
 *
*/

if (LANG == '') {
    $lang = $doc->html->getAttribute('lang');
    if (! $lang) {
        $lang = DEFAULT_LANGUAGE;
    }
    
    $url = join_url(BASEURL, $lang, '');
    redirect($url);
}


/*
 * Read and automate navigation menus
 *
*/

$pages_map = array();
$localized_url = array();

function maybe_follow_redirect($el) {
    $ul = xpath_first_node($el, 
        './descendant-or-self::ul[@class~="'.NAV_GOTOFIRST_CLASSNAME.'"]');
    if (! $ul) {
        return;
    }
    
    $url = xpath_first_node($ul, 
        './li[1]/a[@lang="'.LANG.'"]/@href',
        './li[1]/a/@href'
    )->value;
    redirect($url);
}

function record_localized_url($li) {
    global $pages_map, $localized_url;
    
    static $historics = array();
    $langs = xpath($li, './a[@lang]/@lang');
    $as = (count($langs)) ? 
        xpath($li, './a[@lang]') : 
        xpath($li, './a');
    foreach ($as as $a) {
        $name = $a->getAttribute('href');
        $lang = ($a->hasAttribute('lang')) ? 
            $a->getAttribute('lang') : 'C';
        $id = $lang.'::'.$name;
        if (in_array($id, $historics)) {
            return;
        }
        $historics[] = $id;
        if (! $a->hasAttribute('lang')) {
            $langs = (count($localized_url)) ? 
                array_keys($localized_url):
                array('C');
        }
        else {
            $lang = $a->getAttribute('lang');
            if (array_keys($localized_url) == array('C')) {
                foreach ($langs as $lang_) {
                    $lang_ = $lang_->value;
                    $localized_url[$lang_] = $localized_url['C'];
                }
                unset($localized_url['C']);
            }
            $langs = array($lang);
        }
        foreach ($langs as $lang) {
            if (! array_key_exists($lang, $localized_url)) {
                $localized_url[$lang] = array();
            }
            $localized_url[$lang][] = $name;
        }
    }
}

$navs = xpath($doc, '/html/body//*[@class~="'.NAV_CLASSNAME.'"]');
foreach ($navs as $nav) {
    $local_map = array();
    $lis = xpath($nav, './/li');
    foreach (array_reverse($lis) as $li) {
        $a = xpath_first_node($li, 
            './a[@lang="'.LANG.'"]',
            './a'
        );
        $name = $a->getAttribute('href');
        if ($name == PAGE_NAME) {
            maybe_follow_redirect($li);
            define(PAGE_LABEL, $a->nodeValue);
            add_element_class($li, SELECTEDLINK_CLASSNAME);
            record_localized_url($li);
        }
        add_element_class($li, $name);
        $url = array($name);
        $parent_lis = xpath($li, 
            './ancestor::li[not(count(node()[@class~="'.NAV_CLASSNAME.'"]))]');
        foreach (array_reverse($parent_lis) as $parent_li) {
            $url[] = xpath_first_node($parent_li,
                './a[@lang="'.LANG.'"]/@href',
                './a/@href'
            )->value;
            if ($name == PAGE_NAME) {
                add_element_class($parent_li, SELECTEDPARENTLINK_CLASSNAME);
                record_localized_url($parent_li);
            }
        }
        $depth = count($url);
        $url = join_url(BASEURL, LANG, array_reverse($url), '');
        $a->setAttribute('href', $url);
        if (! array_key_exists($name, $pages_map)) {
            $info = array($a->nodeValue, $name, $url, $depth);
            $pages_map[$name] = $info;
            $local_map[] = $info;
        }
    }
    if (PAGE_NAME == ROOTPAGE_NAME) {
        maybe_follow_redirect($nav);
    }
    $pages_map = array_merge($pages_map, array_reverse($local_map));
}

unset($local_map);
foreach ($localized_url as $lang => $url) {
    $localized_url[$lang] = join_url(
        BASEURL, $lang, array_reverse($url), CHILD_NAME);
}


/*
 * Remove elements for other languages
 *
*/

$elements = xpath($doc, '/html//*[@lang != "'.LANG.'"]');
foreach ($elements as $element) {
    remove_element($element, True);
}


/*
 * Document scope settings
 * 
*/

$doc->head = xpath_first_node($doc, '/html/head');
$doc->body = xpath_first_node($doc, '/html/body');

$doc->html->setAttribute('lang', LANG);
add_element_class($doc->body, PAGE_NAME);


/*
 * Set page title
 *
*/

# get title or create if is absent
$title = xpath_first_node($doc, '/html/head/title');
if (! $title) {
    $title = $doc->createElement('title');
    $last_meta = xpath_first_node($doc, '/html/head/meta[last()]');
    $first_element = xpath_first_node($doc, '/html/head/*[1]');
    if ($last_meta) {
        insert_sibling_node_after($last_meta, $title);
    }
    else
    if ($first_element) {
        insert_sibling_node_before($first_element, $title);
    }
    else {
        $doc->head->appendChild($title);
    }
    indent_element($title);
}

# if no current title value, default to website domain
$title->nodeValue = trim($title->nodeValue);
if (! $title->nodeValue) {
    $title->nodeValue = $_SERVER['HTTP_HOST'].' '.PAGE_TITLE_SEPARATOR;
}

# if title ends with defined separator, append page label
if (substr($title->nodeValue, -1) == PAGE_TITLE_SEPARATOR) {
    $title->nodeValue .= ' '.PAGE_LABEL;
}


/*
 * Add generator META information
 *
*/

$meta = $doc->createElement('meta');
$meta->setAttribute('name', 'generator');
$meta->setAttribute('content', PRODUCT_NAME.' '.PRODUCT_VERSION);

$refnode = xpath_first_node($doc,
    # before first "generator" META
    '/html/head/meta[lower-case(@name)="generator"][1]',
    # after last META
    '/html/head/meta[last()]/following-sibling::*[1]',
    # before TITLE
    '/html/head/title',
    # before every element in HEAD
    '/html/head/*[1]'
);
if ($refnode) {
    insert_sibling_node_before($refnode, $meta);
}
else {
    $doc->head->appendChild($meta);
}
indent_element($meta);


/*
 * Remove unneeded frames + related comment and blank lines
 *
*/

$frames = xpath($doc, 
    '//*[@class~="'.FRAME_CLASSNAME.'" and not(@class~="'.FRAME_NAME.'")]');
    
foreach (array_reverse($frames) as $frame) {
    $related_nodes = xpath($frame,
        # the directly preceding comment
        './preceding-sibling::node()[1]
            /self::comment()',
        # the directly preceding empty text
        './preceding-sibling::node()[1]/self::text()[not(normalize-space())]',
        # the comment directly before the directly preceding empty text
        './preceding-sibling::node()[1]/self::text()[not(normalize-space())]
            /preceding-sibling::node()[1]/self::comment()'
    );
    foreach ($related_nodes as $node) {
        remove_element($node);
    }
    remove_element($frame, True);
}


/*
 * Complete auto-titles
 *
*/

$autotitles = xpath($doc,
    # elements with autotitle class
    '//*[@class~="'.AUTOTITLE_CLASSNAME.'"]',
    # H1 or first empty child when no autotitle class inside H1
    '//h1[not(count(*[@class~="'.AUTOTITLE_CLASSNAME.'"]))]
        /descendant-or-self::*[not(count(*)) and not(normalize-space())][1]'
);

foreach ($autotitles as $autotitle) {
    $autotitle->nodeValue = PAGE_LABEL;
}


/* 
 * Complete auto-links
 * 
*/

$autolinks = xpath($doc, '//*[@class~="'.AUTOLINK_CLASSNAME.'"]');
foreach ($autolinks as $autolink) {
    $href = $autolink->getAttribute('href');
    if (! $href) {
        $autolink->setAttribute('href', DEFAULT_AUTOLINK_HREF);
        continue;
    }
    
    list($type, $value) = explode(AUTOLINK_HREF_SEPARATOR, $href);
    if ($value == '') {
        list($type, $value) = array(AUTOLINK_DEFAULT_TYPENAME, $type);
    }
    
    if ($type == AUTOLINK_PAGE_TYPENAME) {
        $page_name = $value;
        list($label, $name, $url, $depth) = $pages_map[$page_name];
    }
    if ($type == AUTOLINK_LANG_TYPENAME) {
        $lang = $value;
        $url = $localized_url[$lang];
        if (! $url) {
            $url = $localized_url['C'];
            $url = str_replace('C', $lang, $url);
        }
    }
    $autolink->setAttribute('href', $url);
    if ($type == AUTOLINK_PAGE_TYPENAME and $page_name == PAGE_NAME) {
        add_element_class($autolink, SELECTEDLINK_CLASSNAME);
    }
}


/* 
 * Create hierarchical auto-navs
 *
*/

# delete all but numerical keys
foreach (array_reverse($pages_map) as $key => $info) {
    if (is_int($key)) {
        continue;
    }
    unset($pages_map[$key]);
}

$autonavs = xpath($doc, '//*[@class~="'.AUTONAV_CLASSNAME.'"]');
foreach ($autonavs as $autonav) {
    $ul_stack = array();
    foreach ($pages_map as $info) {
        list($label, $name, $url, $depth) = $info;
        if ($name == PAGE_NAME) {
            continue;
        }
        
        if ($depth > count($ul_stack)) {
            $ul = $UL(array('class' => 'depth_'.$depth));
            array_unshift($ul_stack, $ul);
        }
        if ($depth < count($ul_stack)) {
            $ul = array_shift($ul_stack);
            $li = xpath_first_node($ul_stack[0], './li[last()]');
            $li->appendChild($ul);
        }
        $ul = $ul_stack[0];
        $ul->appendChild($LI(NULL, $A(array('href' => $url), $label)));
    }
    while (count($ul_stack) -1) {
        $ul = array_shift($ul_stack);
        $li = xpath_first_node($ul_stack[0], './li[last()]');
        $li->appendChild($ul);
    }
    $ul = $ul_stack[0];
    merge_dom($autonav, $ul);
    indent_element($ul);
}


/*
 * Create alphabetic ordered auto-indexes
 *
*/

$autoindexes = xpath($doc, '//*[@class~="'.AUTOINDEX_CLASSNAME.'"]');
foreach ($autoindexes as $autoindex) {
    $ul = $UL();
    $pages_map = sort_with_accents($pages_map);
    foreach ($pages_map as $info) {
        list($label, $name, $url, $depth) = $info;
        if ($name == PAGE_NAME) {
            continue;
        }
        
        $ul->appendChild($LI(NULL, $A(array('href' => $url), $label)));
    }
    merge_dom($autoindex, $ul);
    indent_element($ul);
}


/*
 * Send output to the client
 *
*/

$doc->normalize();
$xml = $doc->saveXML();
// $xml = indent_xml_string($xml);

echo $xml;

?>