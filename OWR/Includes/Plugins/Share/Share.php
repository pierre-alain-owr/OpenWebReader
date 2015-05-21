<?php
/**
 * Plugin adding share social buttons
 *
 * Javascript code adapted from share42.com, thanks to the author
 */
class Share
{
    public function controller_renderpage($request)
    {
        if('index' === $request->do)
        {
            $shareCode = <<<'HTML'
<script type="text/javascript">
window.addEvent('domready', function() {
/* adapted from share42.com | 28.05.2014 | (c) Dimox */
rP.shareFunc = function(div){if(div.getAttribute('data-url')!=-1)var u=div.getAttribute('data-url');if(div.getAttribute('data-title')!=-1)var t=div.getAttribute('data-title');if(div.getAttribute('data-image')!=-1)var i=div.getAttribute('data-image');if(div.getAttribute('data-description')!=-1)var d=div.getAttribute('data-description');if(div.getAttribute('data-path')!=-1)var f=div.getAttribute('data-path');if(div.getAttribute('data-icons-file')!=-1)var fn=div.getAttribute('data-icons-file');if(!f){function path(name){var sc=document.getElementsByTagName('script'),sr=new RegExp('^(.*/|)('+name+')([#?]|$)');for(var p=0,scL=sc.length;p<scL;p++){var m=String(sc[p].src).match(sr);if(m){if(m[1].match(/^((https?|file)\:\/{2,}|\w:[\/\\])/))return m[1];if(m[1].indexOf("/")==0)return m[1];b=document.getElementsByTagName('base');if(b[0]&&b[0].href)return b[0].href+m[1];else return document.location.pathname.match(/(.*[\/\\])/)[0]+m[1];}}return null;}f=path('share42.js');}if(!u)u=location.href;if(!t)t=document.title;if(!fn)fn='icons.png';function desc(){var meta=document.getElementsByTagName('meta');for(var m=0;m<meta.length;m++){if(meta[m].name.toLowerCase()=='description'){return meta[m].content;}}return'';}if(!d)d=desc();u=encodeURIComponent(u);t=encodeURIComponent(t);t=t.replace(/\'/g,'%27');i=encodeURIComponent(i);d=encodeURIComponent(d);d=d.replace(/\'/g,'%27');var fbQuery='u='+u;if(i!='null'&&i!='')fbQuery='s=100&p[url]='+u+'&p[title]='+t+'&p[summary]='+d+'&p[images][0]='+i;var vkImage='';if(i!='null'&&i!='')vkImage='&image='+i;var s=new Array('"#" onclick="window.open(\'http://www.blogger.com/blog_this.pyra?t&u='+u+'&n='+t+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=440, toolbar=0, status=0\');return false" title="BlogThis!"','"http://bobrdobr.ru/add.html?url='+u+'&title='+t+'&desc='+d+'" title="Share on BobrDobr"','"#" data-count="dlcs" onclick="window.open(\'http://delicious.com/save?url='+u+'&title='+t+'&note='+d+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=710, height=660, toolbar=0, status=0\');return false" title="Save to Delicious"','"http://designbump.com/node/add/drigg/?url='+u+'&title='+t+'" title="Bump it!"','"http://www.designfloat.com/submit.php?url='+u+'" title="Float it!"','"http://digg.com/submit?url='+u+'" title="Share on Digg"','"https://www.evernote.com/clip.action?url='+u+'&title='+t+'" title="Share on Evernote"','"#" data-count="fb" onclick="window.open(\'http://www.facebook.com/sharer.php?m2w&'+fbQuery+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=440, toolbar=0, status=0\');return false" title="Share on Facebook"','"http://www.friendfeed.com/share?title='+t+' - '+u+'" title="Share on FriendFeed"','"#" onclick="window.open(\'http://www.google.com/bookmarks/mark?op=edit&output=popup&bkmk='+u+'&title='+t+'&annotation='+d+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=500, toolbar=0, status=0\');return false" title="Save to Google Bookmarks"','"#" data-count="gplus" onclick="window.open(\'https://plus.google.com/share?url='+u+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=440, toolbar=0, status=0\');return false" title="Share on Google+"','"http://identi.ca/notice/new?status_textarea='+t+' - '+u+'" title="Share on Identi.ca"','"http://www.juick.com/post?body='+t+' - '+u+'" title="Share on Juick"','"#" data-count="lnkd" onclick="window.open(\'http://www.linkedin.com/shareArticle?mini=true&url='+u+'&title='+t+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=600, height=400, toolbar=0, status=0\');return false" title="Share on Linkedin"','"http://www.liveinternet.ru/journal_post.php?action=n_add&cnurl='+u+'&cntitle='+t+'" title="Post to LiveInternet"','"http://www.livejournal.com/update.bml?event='+u+'&subject='+t+'" title="Post to LiveJournal"','"#" data-count="mail" onclick="window.open(\'http://connect.mail.ru/share?url='+u+'&title='+t+'&description='+d+'&imageurl='+i+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=440, toolbar=0, status=0\');return false" title="Share on Мой Мир@Mail.Ru"','"http://memori.ru/link/?sm=1&u_data[url]='+u+'&u_data[name]='+t+'" title="Save to Memori.ru"','"http://www.mister-wong.ru/index.php?action=addurl&bm_url='+u+'&bm_description='+t+'" title="Save to Mister Wong"','"#" onclick="window.open(\'http://chime.in/chimebutton/compose/?utm_source=bookmarklet&utm_medium=compose&utm_campaign=chime&chime[url]'+u+'=&chime[title]='+t+'&chime[body]='+d+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=600, height=440, toolbar=0, status=0\');return false" title="Share on Mixx"','"http://share.yandex.ru/go.xml?service=moikrug&url='+u+'&title='+t+'&description='+d+'" title="Share on Moi Krug"','"http://www.myspace.com/Modules/PostTo/Pages/?u='+u+'&t='+t+'&c='+d+'" title="Share on MySpace"','"http://www.newsvine.com/_tools/seed&save?u='+u+'&h='+t+'" title="Share on Newsvine"','"#" data-count="odkl" onclick="window.open(\'http://www.odnoklassniki.ru/dk?st.cmd=addShare&st._surl='+u+'&title='+t+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=440, toolbar=0, status=0\');return false" title="Share to Odnoklassniki.ru"','"http://pikabu.ru/add_story.php?story_url='+u+'" title="Share on Pikabu.ru"','"#" data-count="pin" onclick="window.open(\'http://pinterest.com/pin/create/button/?url='+u+'&media='+i+'&description='+t+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=600, height=300, toolbar=0, status=0\');return false" title="Pin It"','"http://postila.ru/publish/?url='+u+'&agregator=share42" title="Share on Postila"','"http://reddit.com/submit?url='+u+'&title='+t+'" title="Share on Reddit"','"http://rutwit.ru/tools/widgets/share/popup?url='+u+'&title='+t+'" title="Share on RuTwit.ru"','"http://www.stumbleupon.com/submit?url='+u+'&title='+t+'" title="Share on StumbleUpon"','"#" onclick="window.open(\'http://surfingbird.ru/share?url='+u+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=440, toolbar=0, status=0\');return false" title="Share on Surfingbird"','"http://technorati.com/faves?add='+u+'&title='+t+'" title="Share on Technorati"','"#" onclick="window.open(\'http://www.tumblr.com/share?v=3&u='+u+'&t='+t+'&s='+d+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=450, height=440, toolbar=0, status=0\');return false" title="Share on Tumblr"','"#" data-count="twi" onclick="window.open(\'https://twitter.com/intent/tweet?text='+t+'&url='+u+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=440, toolbar=0, status=0\');return false" title="Share on Twitter"','"#" data-count="vk" onclick="window.open(\'http://vk.com/share.php?url='+u+'&title='+t+vkImage+'&description='+d+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=440, toolbar=0, status=0\');return false" title="Share on VK"','"#" onclick="window.open(\'http://webdiscover.ru/share.php?url='+u+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=440, toolbar=0, status=0\');return false" title="Share on WebDiscover.ru"','"#" onclick="window.open(\'http://bookmarks.yahoo.com/toolbar/savebm?u='+u+'&t='+t+'&d='+d+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=400, toolbar=0, status=0\');return false" title="Save to Yahoo! Bookmarks"','"#" onclick="window.open(\'http://zakladki.yandex.ru/newlink.xml?url='+u+'&name='+t+'&descr='+d+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=550, height=500, toolbar=0, status=0\');return false" title="Save to Yandex Bookmarks"','"#" onclick="window.open(\'http://yosmi.ru/index.php?do=share&url='+u+'\', \'_blank\', \'scrollbars=0, resizable=1, menubar=0, left=100, top=100, width=650, height=400, toolbar=0, status=0\');return false" title="Share on yoSMI"','"" onclick="return rP.shareAddToFav(this,\''+u+'\',\''+t+'\');" title="Save to Browser Favorites"');var l='';for(j=0;j<s.length;j++)l+='<a class="share" rel="nofollow" style="background:url('+f+fn+') -'+24*j+'px 0 no-repeat" href='+s[j]+' target="_blank"></a>';div.innerHTML='<span class="share">'+l+'</span>';};
rP.shareAddToFav = function(a,url,title){try{window.external.AddFavorite(url,title);}catch(e){try{window.sidebar.addPanel(title,url,'');}catch(e){if(typeof(opera)=='object'||window.sidebar){a.rel='sidebar';a.title=title;a.url=url;a.href=url;return true;}}}return false;};
});
</script>
HTML;
            $request->page = str_replace('</body>', $shareCode . '</body>', $request->page);
        }
        elseif('getnewdetails' === $request->do)
        {
            $path = OWR\Config::iGet()->get('surl');
            $icons = 'display.php?f=Share/icons.png';
            $title = htmlspecialchars($request->_datas['title'], ENT_COMPAT, 'UTF-8');
            $shareButtons = <<<HTML
<div id="share-{$request->id}"" class="share" data-path="{$path}" data-title="{$title}" data-description="{$title}" data-icons-file="{$icons}" data-url="{$request->_datas['url']}"></div>
<script type="text/javascript" data-owr="plugins">rP.shareFunc($('share-{$request->id}'));</script>
<style type="text/css">div.share{margin:10px}a.share{display:inline-block;vertical-align:bottom;width:24px;height:24px;margin:0 6px 6px 0;padding:0;outline:none;}</style>
HTML;
            $request->page = $shareButtons . $request->page;
        }
    }
}
