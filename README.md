OpenWebReader is a PHP, multi-user feed aggregator.
It is designed for becoming a RIA and uses AJAX technology as main rich interface amplifyer.
It is released under the GPL license, see COPYING.
Copyright (c) 2009, Pierre-Alain Mignot <pierre-alain@openwebreader.org>

- Features :
    * Support for ATOM, RSS 0.92, RSS 1 (RDF), RSS 2 formats
    * Import/export feeds in OPML format
    * Multi-lingual interface (English and French at the moment), sober and fast, powered by AJAX
    * Feeds tagging, with or without use of drag'n'drop
    * Multi-users, two level of rights (user, administrator)
    * Documented source code
    * REST API
    * Full-text search
    * OpenSearch compatible
    * Gateway system with automatic authentication
    * Mark some news easily into most spreaded services
    * SSL support (HTTPS)
    * Caching system (both templates and SQL results)
    * E-tags support
    * Feeds auto-dicovery
    * 304 HTTP code status (not modified) support
    * Favicons support (requires Imagick if you want to validate the icon's integrity)

Some tricks :
- adding OpenWebReader in your browser search bar (Firefox example) :
    * click on the button on the left of the search bar in Firefox
    * click on "Add < Search in OWR >"
    * now OWR is in the list, you just have to select it, and enter your keywords !

- adding a stream externally
    * you NEED to be logged in
    * the url will be like http://yourdomain.tld/PATH/TO/OWR/?do=add&url=[the_url] or http://yourdomain.tld/PATH/TO/OWR/add?url=[the_url] following your configuration

- adding OpenWebReader to your dynamic bookmarks reader (Firefox example)
    * type "about:config" in the url bar
    * READ and accept the warning
    * in the search field, type "browser.contentHandlers.types", you will see examples like google or netvibes
    * add the three lines corresponding at your configuration, like those examples :
        => name="browser.contentHandlers.types.6.title" value="OpenWebReader"
        => name="browser.contentHandlers.types.6.type" value="application/vnd.mozilla.maybe.feed"
        => name="browser.contentHandlers.types.6.uri" value="https://yourdomain.tld/PATH/TO/OWR/?do=add&url=%s"


Thanks to all of them :
- Logos (png and animated) by Bruno Perles <brunto@ahtna.org> <http://atnos.com>
- Icons from http://www.famfamfam.com/lab/icons/silk/preview.php, see images/README.
- MP3 flash player by <http://alsacreations.fr> <http://www.alsacreations.fr/dewplayer>
- FLV flash player by <http://alsacreations.fr> <http://www.alsacreations.fr/dewtube>
- HTMLPurifier as input purifier <http://htmlpurifier.org>
- mootools as javascript library <http://mootools.net>
