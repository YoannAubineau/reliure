Reliure is a standalone, simple, generic PHP controler that makes a single web page look like multiple ones. The urls pointing to these multiple virtual web pages are derived from the navigation plan the real one provides.

## Quick example

Here is an example. If you create a web page that contains this following HTML snippet: 

```
<ul id="nav">
  <li><a href="about">Who we are</a></li>
  <li><a href="services">What we do</a></li>
  <li><a href="contact">How to get in touch</a></li>
</ul>
```

Reliure will accept the three `./about/`, `./services/` and `./contact/` urls and serve a different virtual web page for each of them, without having to create neither the directory structure nor the web-pages themselves.

The content of these virtual pages depends on which frame is selected. For example: 

```
<div class="frame about">
    <p><em>MyWebSite</em> is a dummy web site whose purpose is only to demonstrate how Reliure works.</p>
</div

<div class="frame contact">
    <p><a href="http://www.mywebsite.com/">Visit our MyWebSite</a></p>
    <p><a href="mailto:contact@mywebsite.com">Send us an email</a></p>
    <p><a href="http://www.twitter.com/mywebsite/">Follow us on Twitter</a></p>
</div>
```

When the request url is `./about/`, Reliure removes all frames but the one named about, so that contact informations will not appear. On the contrary, when the request url is `./contact/`, Reliure removes the about frame. The logo, however, is never removed, as it's not a frame. It appears on all web-pages, without anyone having to duplicate the markup.

To make a bloc appear on some web-pages only, all one needs to do is mark it as a frame and name it with the page names it has to appear on. In the following example:

```
<div class="frame services contact">
  <a href="http://www.website.com/">Back to homepage</a>
</div>
```

the Back to homepage link will appear on the services and contact pages but not on the aboutpage.

## More features

Reliure as a lot more to offer. It can:
* set selected class on selected navigation menus
* fill the web-page title and any h1 with current page's name
* expand inter-pages links' href
* handle multilingual web-pages and urls
* generate a page index and a site map

Give it a try!

## Documentation

A more extensive documentation is yet to be written. Check back later!
