<?php

require_once('simpletest/autorun.php');
require_once('simpletest/web_tester.php');

define(BASEPATH, dirname(__FILE__));

require_once(BASEPATH.'/../src/config.php');
require_once(BASEPATH.'/../src/dom.php');
require_once(BASEPATH.'/../src/xpath.php');

define(RELIURE_URL, 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'../');
define(CONFIG_FILEPATH, BASEPATH.'/config.ini');
define(TMP_DIRPATH, BASEPATH.'/tmp/');


function in_directory($filename, $dirpath) {
    $filepath = rtrim($dirpath, '/').'/'.$filename;
    $r = file_exists($filepath);
    return $r;
}


function dump_into_temp_file($content, $basedir, $prefix=NULL) {
    $filepath = tempnam($basedir, $prefix.'_');
    if (! in_directory(basename($filepath), $basedir)) {
        echo '[ERROR] Please make sure '.$basedir.' is writable.';
        exit(1);
    }
    $fd = fopen($filepath, 'w');
    fwrite($fd, $content);
    fclose($fd);
    return $filepath;
}


abstract
class ReliureTestCase extends WebTestCase {

    function _get($template_path, $request_uri, $config_path) {
        $query = http_build_query(array(
            'template' => $template_path,
            'url' => $request_uri,
            'config' => $config_path
        ));
        $url = RELIURE_URL.'?'.$query;
        $this->get($url);
    }

    function _load() {
        $content = $this->_browser->getContent();
        if (! $content) {
            return NULL;
        }
        $doc = new DOMDocument();
        $doc->loadHTML($content);
        return $doc;
    }
    
    function loadThroughReliure($request_uri, $content) {
        $template_path = dump_into_temp_file($content, TMP_DIRPATH);
        $this->_get($template_path, $request_uri, CONFIG_FILEPATH);
        $dom = $this->_load();
        unlink($template_path);
        return $dom;
    }
    
}


class TestDocument extends ReliureTestCase {

    function setUp() {
        $this->doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><base href="/path/to/"/></head>
            <body>
                <ul class="nav">
                    <li><a lang="en" href="home">Homepage</a></li>
                </ul>
                <div id="baseurl"><?=BASEURL?></div>
            </body></html>
        ');
    }

    function test_baseurl() {
        $value = xpath_first_node($this->doc, '//*[@id="baseurl"]')->nodeValue;
        $this->assertEqual($value, '/path/to');
    }

    function test_bodyClassContainsPageName() {
        $class = xpath_first_node($this->doc, '//body/@class')->value;
        $this->assertTrue(strpos($class, 'home') !== False);
    }
}


class TestLanguageRedirect extends ReliureTestCase {
    
    function test_redirectToDefaultLanguage() {
        $this->setMaximumRedirects(0);
        $this->doc = $this->loadThroughReliure('/', '
            <!DOCTYPE html><html><head></head><body>
            </body></html>
        ');
        $this->assertHeader('Location', '/eo/');
    }

    function test_redirectToSpecifiedLanguage() {
        $this->setMaximumRedirects(0);
        $this->doc = $this->loadThroughReliure('/', '
            <!DOCTYPE html><html lang="fr"><head></head><body>
            </body></html>
        ');
        $this->assertHeader('Location', '/fr/');
    }
}


class TestLanguageSelection extends ReliureTestCase {
    
    function setUp() {
        $this->doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
                <div id="french" lang="fr"/>
                <div id="english" lang="en"/>
                <div id="C"/>
            </body></html>
        ');
    }
    
    function test_frenchElementRemoved() {
        $el = xpath_first_node($this->doc, '//*[@id="french"]');
        $this->assertFalse($el);
    }
    
    function test_englishElementRemains() {
        $el = xpath_first_node($this->doc, '//*[@id="english"]');
        $this->assertTrue($el);
    }
    
    function test_neutralElementRemains() {
        $el = xpath_first_node($this->doc, '//*[@id="C"]');
        $this->assertTrue($el);
    }
    
    function test_htmlElementHasLangAttribute() {
        $lang = xpath_first_node($this->doc, '/html/@lang')->value;
        $this->assertEqual($lang, 'en');
    }
}


class TestContentSelectors extends ReliureTestCase {

    function setUp() {
        $this->doc = $this->loadThroughReliure('/en/more/more/products/', '
            <!DOCTYPE html><html><head></head><body>
                <div id="lang"><?=LANG?></div>
                <div id="page_name"><?=PAGE_NAME?></div>
                <div id="child_name"><?=CHILD_NAME?></div>
                <div id="frame_name"><?=FRAME_NAME?></div>
            </body></html>
        ');
    }
    
    function test_LANG() {
        $value = xpath_first_node($this->doc, '//*[@id="lang"]')->nodeValue;
        $this->assertEqual($value, 'en');
    }

    function test_PAGE_NAME() {
        $value = xpath_first_node($this->doc, '//*[@id="page_name"]')->nodeValue;
        $this->assertEqual($value, 'products');
    }

    function test_CHILD_NAME() {
        $value = xpath_first_node($this->doc, '//*[@id="child_name"]')->nodeValue;
        $this->assertEqual($value, '');
    }

    function test_FRAME_NAME() {
        $value = xpath_first_node($this->doc, '//*[@id="frame_name"]')->nodeValue;
        $this->assertEqual($value, 'products');
    }
}


class TestChildContentSelectors extends ReliureTestCase {
    
    function setUp() {
        $this->doc = $this->loadThroughReliure('/en/more/more/products/12345', '
            <!DOCTYPE html><html><head></head><body>
                <div id="lang"><?=LANG?></div>
                <div id="page_name"><?=PAGE_NAME?></div>
                <div id="child_name"><?=CHILD_NAME?></div>
                <div id="frame_name"><?=FRAME_NAME?></div>
            </body></html>
        ');
    }
    
    function test_LANG() {
        $value = xpath_first_node($this->doc, '//*[@id="lang"]')->nodeValue;
        $this->assertEqual($value, 'en');
    }

    function test_PAGE_NAME() {
        $value = xpath_first_node($this->doc, '//*[@id="page_name"]')->nodeValue;
        $this->assertEqual($value, 'products');
    }

    function test_CHILD_NAME() {
        $value = xpath_first_node($this->doc, '//*[@id="child_name"]')->nodeValue;
        $this->assertEqual($value, '12345');
    }

    function test_FRAME_NAME() {
        $value = xpath_first_node($this->doc, '//*[@id="frame_name"]')->nodeValue;
        $this->assertEqual($value, 'products_child');
    }
}


class TestRootPageContentSelectors extends ReliureTestCase {
    
    function setUp() {
        $this->doc = $this->loadThroughReliure('/en/', '
            <!DOCTYPE html><html><head></head><body>
                <div id="lang"><?=LANG?></div>
                <div id="page_name"><?=PAGE_NAME?></div>
                <div id="child_name"><?=CHILD_NAME?></div>
                <div id="frame_name"><?=FRAME_NAME?></div>
            </body></html>
        ');
    }
    
    function test_LANG() {
        $value = xpath_first_node($this->doc, '//*[@id="lang"]')->nodeValue;
        $this->assertEqual($value, 'en');
    }

    function test_PAGE_NAME() {
        $value = xpath_first_node($this->doc, '//*[@id="page_name"]')->nodeValue;
        $this->assertEqual($value, 'root');
    }

    function test_CHILD_NAME() {
        $value = xpath_first_node($this->doc, '//*[@id="child_name"]')->nodeValue;
        $this->assertEqual($value, '');
    }

    function test_FRAME_NAME() {
        $value = xpath_first_node($this->doc, '//*[@id="frame_name"]')->nodeValue;
        $this->assertEqual($value, 'root');
    }
}


class TestNavigationSelectionClasses extends ReliureTestCase {
    
    function setUp() {
        $this->doc = $this->loadThroughReliure('/en/fruits/apple/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a id="fruits" href="fruits">Fruits</a>
                <ul>
                    <li><a id="apple" href="apple">Apple</a></li>
                    <li><a id="orange" href="orange">Orange</a></li>
            </ul></li></ul>
            </body></html>
        ');
    }
    
    # selected class
    
    function test_appleMenuHasSelectedClass() {
        $li = xpath_first_node($this->doc, '//a[@id="apple"]/..');
        $this->assertTrue(has_element_class($li, 'selected'));
    }

    function test_orangeMenuHasNotSelectedClass() {
        $li = xpath_first_node($this->doc, '//a[@id="orange"]/..');
        $this->assertFalse(has_element_class($li, 'selected'));
    }

    function test_fruitsMenuHasNotSelectedClass() {
        $li = xpath_first_node($this->doc, '//a[@id="fruits"]/..');
        $this->assertFalse(has_element_class($li, 'selected'));
    }
    
    # selected_parent class
    
    function test_fruitsMenuHasSelectedParentClass() {
        $li = xpath_first_node($this->doc, '//a[@id="fruits"]/..');
        $this->assertTrue(has_element_class($li, 'selected_parent'));
    }

    function test_appleMenuHasNotSelectedParentClass() {
        $li = xpath_first_node($this->doc, '//a[@id="apple"]/..');
        $this->assertFalse(has_element_class($li, 'selected_parent'));
    }

    function test_orangeMenuHasNotSelectedParentClass() {
        $li = xpath_first_node($this->doc, '//a[@id="orange"]/..');
        $this->assertFalse(has_element_class($li, 'selected_parent'));
    }
}


class TestNavigationOwnNameClass extends ReliureTestCase {

    function setUp() {
        $this->doc = $this->loadThroughReliure('/en/fruits/apple/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a id="fruits" href="fruits">Fruits</a>
                <ul>
                    <li><a id="apple" href="apple">Apple</a></li>
                    <li><a id="orange" href="orange">Orange</a></li>
            </ul></li></ul>
            </body></html>
        ');
    }

    function test_orangeMenuHasOwnNameClass() {
        $li = xpath_first_node($this->doc, '//a[@id="orange"]/..');
        $this->assertTrue(has_element_class($li, 'orange'));
    }

    function test_appleMenuHasOwnNameClass() {
        $li = xpath_first_node($this->doc, '//a[@id="apple"]/..');
        $this->assertTrue(has_element_class($li, 'apple'));
    }

    function test_fruitsMenuHasOwnNameClass() {
        $li = xpath_first_node($this->doc, '//a[@id="fruits"]/..');
        $this->assertTrue(has_element_class($li, 'fruits'));
    }
}


class TestNavigationHrefs extends ReliureTestCase {

    function setUp() {
        $this->doc = $this->loadThroughReliure('/en/fruits/apple/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a id="fruits" href="fruits">Fruits</a>
                <ul>
                    <li><a id="apple" href="apple">Apple</a></li>
                    <li><a id="orange" href="orange">Orange</a></li>
            </ul></li></ul>
            </body></html>
        ');
    }

    function test_fruitMenuHref() {
        $href = xpath_first_node($this->doc, '//a[@id="fruits"]/@href')->value;
        $this->assertEqual($href, '/en/fruits/');
    }

    function test_orangeMenuHref() {
        $href = xpath_first_node($this->doc, '//a[@id="orange"]/@href')->value;
        $this->assertEqual($href, '/en/fruits/orange/');
    }

    function test_appleMenuHref() {
        $href = xpath_first_node($this->doc, '//a[@id="apple"]/@href')->value;
        $this->assertEqual($href, '/en/fruits/apple/');
    }
}


class TestNavigationWithSameNamedItems extends ReliureTestCase {

    function test_twiceInSameLanguage() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav"><li><a href="contact">Contact</a></li></ul>
            <ul class="nav"><li><a href="contact">Contact us</a></li></ul>
            </body></html>
        ');
        $hrefs = xpath($doc, '//a/@href');
        foreach ($hrefs as $href) {
            $this->assertEqual($href->value, '/en/contact/');
        }
    }

    function test_twiceInSameLanguageWithTranslation() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a lang="en" href="home">Home</a>
                    <a lang="fr" href="accueil">Accueil</a>
            </li></ul>
            <a class="autolink" href="lang:fr"/>
            <ul class="nav">
                <li><a lang="en" href="home">Home</a>
                    <a lang="fr" href="accueil">Accueil</a>
            </li></ul>
            </body></html>
        ');
        $href = xpath_first_node($doc, '//a[@class]/@href')->value;
        $this->assertEqual($href, '/fr/accueil/');
    }

    function test_twiceInBothLanguages() {
        $doc = $this->loadThroughReliure('/fr/contact/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a lang="en" href="contact">Contact</a>
                    <a lang="fr" href="contact">Contact</a>
                </li>
            </ul>
            <ul class="nav">
                <li><a lang="en" href="contact">Contact</a>
                    <a lang="fr" href="contact">Contact</a>
                </li>
            </ul>
            <a class="autolink" href="lang:en"/>
            </body></html>
        ');
        $href = xpath_first_node($doc, '//a[@class]/@href')->value;
        $this->assertEqual($href, '/en/contact/');
    }
}


class TestNavigationRedirect extends ReliureTestCase {

    function test_redirectToFirstMenu() {
        $this->setMaximumRedirects(0);
        $this->doc = $this->loadThroughReliure('/en/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav gotofirst">
                <li><a href="fruits">Fruits</a>
                <ul>
                    <li><a href="apple">Apple</a></li>
                    <li><a href="orange">Orange</a></li>
            </ul></li></ul>
            </body></html>
        ');
        $this->assertHeader('Location', '/en/fruits/');
    }
    
    function test_redirectToFirstMenu_NavigationWrapped() {
        $this->setMaximumRedirects(0);
        $this->doc = $this->loadThroughReliure('/en/', '
            <!DOCTYPE html><html><head></head><body>
            <div class="nav">
            <ul class="gotofirst">
                <li><a href="fruits">Fruits</a>
                <ul>
                    <li><a href="apple">Apple</a></li>
                    <li><a href="orange">Orange</a></li>
            </ul></li></ul></div>
            </body></html>
        ');
        $this->assertHeader('Location', '/en/fruits/');
    }

    function test_redirectToFirstSubMenu() {
        $this->setMaximumRedirects(0);
        $this->doc = $this->loadThroughReliure('/en/fruits/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a href="fruits">Fruits</a>
                <ul class="gotofirst">
                    <li><a href="apple">Apple</a></li>
                    <li><a href="orange">Orange</a></li>
            </ul></li></ul>
            </body></html>
        ');
        $this->assertHeader('Location', '/en/fruits/apple/');
    }
}


class TestPageTitleContent extends ReliureTestCase {

    function test_setIfEmpty() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><title></title></head>
            <body>
                <ul class="nav">
                    <li><a lang="en" href="home">Homepage</a></li>
                </ul>
            </body></html>
        ');
        $this->assertTitle($_SERVER['HTTP_HOST'].' | Homepage');
    }
    
    function test_setIfBlank() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><title>
            </title></head>
            <body>
                <ul class="nav">
                    <li><a lang="en" href="home">Homepage</a></li>
                </ul>
            </body></html>
        ');
        $this->assertTitle($_SERVER['HTTP_HOST'].' | Homepage');
    }
    
    function test_completeIfEndsWithSeparator() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><title>Test Suite |</title></head>
            <body>
                <ul class="nav">
                    <li><a lang="en" href="home">Homepage</a></li>
                </ul>
            </body></html>
        ');
        $this->assertTitle('Test Suite | Homepage');
    }
    
    function test_completeIfEndsWithSeparatorFollowedWithBlanks() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><title>Test Suite |
            </title></head>
            <body>
                <ul class="nav">
                    <li><a lang="en" href="home">Homepage</a></li>
                </ul>
            </body></html>
        ');
        $this->assertTitle('Test Suite | Homepage');
    }
    
    function test_ignoreIfDoesntEndWithSeparator() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><title>Test Suite</title></head>
            <body>
                <ul class="nav">
                    <li><a lang="en" href="home">Homepage</a></li>
                </ul>
            </body></html>
        ');
        $this->assertTitle('Test Suite');
    }
    
    function test_ignoreIfContainsSeparatorNotAtEnd() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><title>Test Suite | Test</title></head>
            <body>
                <ul class="nav">
                    <li><a lang="en" href="home">Homepage</a></li>
                </ul>
            </body></html>
        ');
        $this->assertTitle('Test Suite | Test');
    }

    function test_ignoreIfDoesntEndWithSeparatorButTrimItAnyway() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><title>   Test Suite | Test
            </title></head>
            <body>
                <ul class="nav">
                    <li><a lang="en" href="home">Homepage</a></li>
                </ul>
            </body></html>
        ');
        $this->assertTitle('Test Suite | Test');
    }

}


class TestPageTitlePosition extends ReliureTestCase {

    function test_afterLastMeta() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><meta/><meta/><meta/><base href="/"/></head>
            <body>
                <ul class="nav">
                    <li><a lang="en" href="home">Homepage</a></li>
                </ul>
            </body></html>
        ');
        $title = xpath_first_node($doc, '//title');
        $element = xpath_first_node($doc, '//meta[last()]/following-sibling::*[1]');
        $this->assertTrue($title->isSameNode($element));
    }

    function test_beforeEveryElementIfNoMeta() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><base href="/"/></head>
            <body>
                <ul class="nav">
                    <li><a lang="en" href="home">Homepage</a></li>
                </ul>
            </body></html>
        ');
        $title = xpath_first_node($doc, '//title');
        $element = xpath_first_node($doc, '/html/head/base/preceding-sibling::*[1]');
        $this->assertTrue($title->isSameNode($element));
    }
}


class TestGeneratorMeta extends ReliureTestCase {

    function test_checkContent() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head></head>
            <body></body></html>
        ');
        $content = xpath_first_node($doc, 
            '//meta[lower-case(@name)="generator"]/@content')->nodeValue;
        $this->assertEqual($content, 'Reliure 0.2');
    }

    function test_beforeOtherGeneratorMetas() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><base/><meta name="Generator" content="human"/></head>
            <body></body></html>
        ');
        $first_meta = xpath_first_node($doc, 
            '//meta[lower-case(@name)="generator"]');
        $meta = xpath_first_node($doc, 
            '//meta[lower-case(@name)="generator" and @content="Reliure 0.2"]');
        $this->assertEqual($first_meta, $meta);
    }

    function test_afterLastMeta() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><meta name="foo" content="bar"/><title/></head>
            <body></body></html>
        ');
        $last_meta = xpath_first_node($doc, '//meta[last()]');
        $meta = xpath_first_node($doc, 
            '//meta[lower-case(@name)="generator" and @content="Reliure 0.2"]');
        $this->assertEqual($last_meta, $meta);
    }

    function test_beforeTitle() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><base/><title/></head>
            <body></body></html>
        ');
        $before_title = xpath_first_node($doc, 
            '//title/preceding-sibling::*[1]');
        $meta = xpath_first_node($doc, 
            '//meta[lower-case(@name)="generator" and @content="Reliure 0.2"]');
        $this->assertEqual($before_title, $meta);
    }

    function test_beforeEveryElementInHead() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head><base/></head>
            <body></body></html>
        ');
        $first_element = xpath_first_node($doc, '//head/*[1]');
        $meta = xpath_first_node($doc, 
            '//meta[lower-case(@name)="generator" and @content="Reliure 0.2"]');
        $this->assertEqual($first_element, $meta);
    }

    function test_intoHead() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html>
            <head></head>
            <body></body></html>
        ');
        $first_element = xpath_first_node($doc, '//head/*[1]');
        $meta = xpath_first_node($doc, 
            '//meta[lower-case(@name)="generator" and @content="Reliure 0.2"]');
        $this->assertEqual($first_element, $meta);
    }

}


class TestFrameRemoval extends ReliureTestCase {

    function test_anonymousFrameRemoved() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <div class="frame"/>
            </body></html>
        ');
        $frame = xpath_first_node($doc, '//*[@class~="frame"]');
        $this->assertFalse($frame);
    }

    function test_notSelectedFrameRemoved() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <div class="frame" contact"/>
            </body></html>
        ');
        $frame = xpath_first_node($doc, 
            '//*[@class~="frame" and @class~="contact"]');
        $this->assertFalse($frame);
    }

    function test_selectedFrameNotRemoved() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <div class="frame home"/>
            </body></html>
        ');
        $frame = xpath_first_node($doc, 
            '//*[@class~="frame" and @class~="home"]');
        $this->assertTrue($frame);
    }

    function test_directlyPrecedingCommentRemoved() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <!-- comment --><div class="frame"/>
            </body></html>
        ');
        $frame = xpath_first_node($doc, '/html/body//comment()');
        $this->assertFalse($frame);
    }

    function test_directlyPrecedingBlankTextRemoved() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <div class="frame"/>
            </body></html>
        ');
        $inner = xpath_first_node($doc, '/html/body')->nodeValue;
        $this->assertFalse($inner);
    }

    function test_commentDirectlyBeforeTheDirectlyPrecedingBlankTextRemoved() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <!-- comment -->   <div class="frame"/>
            </body></html>
        ');
        $frame = xpath_first_node($doc, '/html/body//comment()');
        $this->assertFalse($frame);
        $inner = xpath_first_node($doc, '/html/body')->nodeValue;
        $this->assertFalse($frame);
    }
}


class TestAutoTitle extends ReliureTestCase {
    
    function test_h1() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav"><li><a lang="en" href="home">Homepage</a></li></ul>
            <h1/>
            </body></html>
        ');
        $h1 = xpath_first_node($doc, '//h1');
        $this->assertEqual($h1->nodeValue, 'Homepage');
    }

    function test_h1DeepChild() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav"><li><a lang="en" href="home">Homepage</a></li></ul>
            <h1><div><p><span></span></p></div></h1>
            </body></html>
        ');
        $span = xpath_first_node($doc, '//h1//span');
        $this->assertEqual($span->nodeValue, 'Homepage');
    }

    function test_h1FirstEmptyChild() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav"><li><a lang="en" href="home">Homepage</a></li></ul>
            <h1><div>Foo</div><div><p><span></span></p></div><div></div></h1>
            </body></html>
        ');
        $span = xpath_first_node($doc, '//h1//span');
        $this->assertEqual($span->nodeValue, 'Homepage');
    }

    function test_elementWithAutotitleClass() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav"><li><a lang="en" href="home">Homepage</a></li></ul>
            <div class="autotitle"></div>
            </body></html>
        ');
        $el = xpath_first_node($doc, '//*[@class~="autotitle"]');
        $this->assertEqual($el->nodeValue, 'Homepage');
    }
    
}


class TestAutolinks extends ReliureTestCase {
    
    function test_defaultAutolinkHref() {
        $doc = $this->loadThroughReliure('/en/fruits/', '
            <!DOCTYPE html><html><head></head><body>
            <a class="autolink"/>
            </body></html>
        ');
        $href = xpath_first_node($doc, '//a[@class~="autolink"]/@href')->value;
        $this->assertEqual($href, '#');
    }

    function test_pageAutolink() {
        $doc = $this->loadThroughReliure('/en/apple/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a lang="en" href="fruits">Fruits</a>
                <ul>
                    <li><a lang="en" href="apple">Apple</a></li>
            </ul></li></ul>
            <a class="autolink" href="page:apple"/>
            </body></html>
        ');
        $href = xpath_first_node($doc, '//a[@class~="autolink"]/@href')->value;
        $this->assertEqual($href, '/en/fruits/apple/');
    }

    function test_selectedPageAutolink() {
        $doc = $this->loadThroughReliure('/en/apple/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a lang="en" href="fruits">Fruits</a>
                <ul>
                    <li><a lang="en" href="apple">Apple</a></li>
            </ul></li></ul>
            <a class="autolink" href="page:apple"/>
            </body></html>
        ');
        $class = xpath_first_node($doc, '//a[@class~="autolink"]/@class')->value;
        $this->assertTrue(strpos($class, 'selected'));
    }

    function test_pageAutolinkWithDefaultType() {
        $doc = $this->loadThroughReliure('/en/fruitsapple/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a href="fruits">Fruits</a>
                    <ul>
                    <li><a lang="en" href="apple">Apple</a>
                        <a lang="fr" href="pomme">Pomme</a>
            </li></ul></li></ul>
            <a class="autolink" href="apple"/>
            </body></html>
        ');
        $href = xpath_first_node($doc, '//a[@class~="autolink"]/@href')->value;
        $this->assertEqual($href, '/en/fruits/apple/');
    }
}


class TestLangAutolinks extends ReliureTestCase {

    function test_langAutolink() {
        $doc = $this->loadThroughReliure('/en/apple/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a href="fruits">Fruits</a>
                    <ul>
                    <li><a lang="en" href="apple">Apple</a>
                        <a lang="fr" href="pomme">Pomme</a>
            </li></ul></li></ul>
            <a class="autolink" href="lang:fr"/>
            </body></html>
        ');
        $href = xpath_first_node($doc, '//a[@class~="autolink"]/@href')->value;
        $this->assertEqual($href, '/fr/fruits/pomme/');
    }

    function test_langAutolinkWithMultilingualBranch() {
        $doc = $this->loadThroughReliure('/en/countryside/fruits/apple/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a lang="en" href="countryside">Country side</a>
                    <a lang="fr" href="campagne">Campagne</a>
                <ul>
                    <li><a href="fruits">Fruits</a>
                    <ul>
                        <li><a lang="en" href="apple">Apple</a>
                            <a lang="fr" href="pomme">Pomme</a>
            </li></ul></li></ul></li></ul>
            <a class="autolink" 
                href="lang:fr"/>
            </body></html>
        ');
        $href = xpath_first_node($doc, '//a[@class~="autolink"]/@href')->value;
        $this->assertEqual($href, '/fr/campagne/fruits/pomme/');
    }

    function test_langAutolinkWithMultilingualLeaf() {
        $doc = $this->loadThroughReliure('/en/countryside/food/fruits/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a lang="en" href="countryside">Country side</a>
                    <a lang="fr" href="campagne">Campagne</a>
                <ul>
                    <li><a lang="en" href="food">Food</a>
                        <a lang="fr" href="nourriture">Nourriture</a>
                    <ul>
                        <li><a href="fruits">Fruits</a>
            </li></ul></li></ul></li></ul>
            <a class="autolink" href="lang:fr"/>
            </body></html>
        ');
        $href = xpath_first_node($doc, '//a[@class~="autolink"]/@href')->value;
        $this->assertEqual($href, '/fr/campagne/nourriture/fruits/');
    }

    function test_langAutolinkWithMultilingualPath() {
        $doc = $this->loadThroughReliure('/en/nature/fruits/', '
            <!DOCTYPE html><html><head></head><body>
            <ul class="nav">
                <li><a href="nature">Nature</a>
                <ul>
                    <li><a href="fruits">Fruits</a>
            </li></ul></li></ul>
            <a class="autolink" href="lang:fr"/>
            </body></html>
        ');
        $href = xpath_first_node($doc, '//a[@class~="autolink"]/@href')->value;
        $this->assertEqual($href, '/fr/nature/fruits/');
    }

}


class TestAutoIndex extends ReliureTestCase {
    
    function test_baseFeature() {
        $doc = $this->loadThroughReliure('/en/sitemap/', '
            <!DOCTYPE html><html><head></head><body>
                <ul class="nav">
                    <li><a href="home">Home</a></li>
                    <li><a href="fruits">Fruits</a>
                    <ul>
                        <li><a href="apple">Apple</a></li>
                        <li><a href="orange">Orange</a></li>
                </ul></li></ul>
                <div class="autoindex"/>
            </body></html>
        ');
        $labels = xpath($doc, '//*[@class~=autoindex]//a/text()');
        foreach ($labels as $key => $value) {
            $labels[$key] = $value->nodeValue;
        }
        $this->assertEqual($labels, array(
            'Apple', 'Fruits', 'Home', 'Orange'
        ));
    }

    function test_currentPageNotListed() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
                <ul class="nav">
                    <li><a href="home">Home</a></li>
                    <li><a href="fruits">Fruits</a>
                    <ul>
                        <li><a href="apple">Apple</a></li>
                        <li><a href="orange">Orange</a></li>
                </ul></li></ul>
                <div class="autoindex"/>
            </body></html>
        ');
        $labels = xpath($doc, '//*[@class~=autoindex]//a/text()');
        foreach ($labels as $key => $value) {
            $labels[$key] = $value->nodeValue;
        }
        $this->assertEqual($labels, array(
            'Apple', 'Fruits', 'Orange'
        ));
    }

    function test_multiLanguages() {
        $doc = $this->loadThroughReliure('/fr/sitemap/', '
            <!DOCTYPE html><html><head></head><body>
                <ul class="nav">
                    <li><a lang="en" href="home">Home</a>
                        <a lang="fr" href="accueil">Accueil</a>
                    </li>
                    <li><a href="fruits">Fruits</a>
                    <ul>
                        <li><a lang="en" href="apple">Apple</a>
                            <a lang="fr" href="pomme">Pomme</a>
                        </li>
                        <li><a href="orange">Orange</a></li>
                </ul></li></ul>
                <div class="autoindex"/>
            </body></html>
        ');
        $labels = xpath($doc, '//*[@class~=autoindex]//a/text()');
        foreach ($labels as $key => $value) {
            $labels[$key] = $value->nodeValue;
        }
        $this->assertEqual($labels, array(
            'Accueil', 'Fruits', 'Orange', 'Pomme'
        ));
    }
    
    function test_accentSorting() {
        $doc = $this->loadThroughReliure('/fr/sitemap/', '
            <!DOCTYPE html><html><head></head><body>
                <ul class="nav">
                    <li><a href="accueil">Accueil</a></li>
                    <li><a href="fruits">Fruits</a>
                    <ul>
                        <li><a href="epinards">Épinards</a></li>
                        <li><a href="orange">Orange</a></li>
                </ul></li></ul>
                <div class="autoindex"/>
            </body></html>
        ');
        $labels = xpath($doc, '//*[@class~=autoindex]//a/text()');
        foreach ($labels as $key => $value) {
            $labels[$key] = $value->nodeValue;
        }
        $this->assertEqual($labels, array(
            'Accueil', 'Épinards', 'Fruits', 'Orange'
        ));
    }
}


class TestAutonav extends ReliureTestCase {
    
    function test_baseFeature() {
        $doc = $this->loadThroughReliure('/en/sitemap/', '
            <!DOCTYPE html><html><head></head><body>
                <ul class="nav">
                    <li><a href="home">Home</a></li>
                    <li><a href="fruits">Fruits</a>
                    <ul>
                        <li><a href="apple">Apple</a></li>
                        <li><a href="orange">Orange</a></li>
                </ul></li></ul>
                <ul class="autonav"/>
            </body></html>
        ');
        $labels = xpath($doc, '//*[@class~=autonav]//a/text()');
        foreach ($labels as $key => $value) {
            $labels[$key] = $value->nodeValue;
        }
        $this->assertEqual($labels, array(
            'Home', 'Fruits', 'Apple', 'Orange'
        ));
    }

    function test_currentPageNotListed() {
        $doc = $this->loadThroughReliure('/en/home/', '
            <!DOCTYPE html><html><head></head><body>
                <ul class="nav">
                    <li><a href="home">Home</a></li>
                    <li><a href="fruits">Fruits</a>
                    <ul>
                        <li><a href="apple">Apple</a></li>
                        <li><a href="orange">Orange</a></li>
                </ul></li></ul>
                <div class="autonav"/>
            </body></html>
        ');
        $labels = xpath($doc, '//*[@class~=autonav]//a/text()');
        foreach ($labels as $key => $value) {
            $labels[$key] = $value->nodeValue;
        }
        $this->assertEqual($labels, array(
            'Fruits', 'Apple', 'Orange'
        ));
    }

    function test_multiLanguages() {
        $doc = $this->loadThroughReliure('/fr/sitemap/', '
            <!DOCTYPE html><html><head></head><body>
                <ul class="nav">
                    <li><a lang="en" href="home">Home</a>
                        <a lang="fr" href="accueil">Accueil</a>
                    </li>
                    <li><a href="fruits">Fruits</a>
                    <ul>
                        <li><a lang="en" href="apple">Apple</a>
                            <a lang="fr" href="pomme">Pomme</a>
                        </li>
                        <li><a href="orange">Orange</a></li>
                </ul></li></ul>
                <div class="autonav"/>
            </body></html>
        ');
        $labels = xpath($doc, '//*[@class~=autonav]//a/text()');
        foreach ($labels as $key => $value) {
            $labels[$key] = $value->nodeValue;
        }
        $this->assertEqual($labels, array(
            'Accueil', 'Fruits', 'Pomme', 'Orange'
        ));
    }
}

?>