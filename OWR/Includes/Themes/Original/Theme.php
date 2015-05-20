<?php
/**
 * Themes class
 * This class is used to manage theme
 *
 * PHP 5
 *
 * OWR - OpenWebReader
 *
 * Copyright (c) 2009, Pierre-Alain Mignot
 *
 * Home page: http://openwebreader.org
 *
 * E-Mail: contact@openwebreader.org
 *
 * All Rights Reserved
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 * @author Pierre-Alain Mignot <contact@openwebreader.org>
 * @copyright Copyright (c) 2009, Pierre-Alain Mignot
 * @license http://www.gnu.org/copyleft/gpl.html
 * @package OWR
 */
namespace OWR\Includes\Themes\Original;
use OWR\Theme as pTheme, OWR\User, OWR\Config, OWR\Dates, OWR\Plugins;

/**
 * Default theme
 *
 * @uses OWR\View the page renderer
 * @uses OWR\User the current user
 * @uses OWR\Config the config instance
 * @uses OWR\Plugins the plugins object
 * @package OWR
 */
class Theme extends pTheme
{
    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Generates login template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of login template
     */
    public function login(array $datas, array $noCacheDatas)
    {
        $noCacheDatas['pagetitle'] = 'OpenWebReader - ' . $this->_view->_('Restricted access');
        $datas['surl'] = Config::iGet()->get('surl');
        $datas['lang'] = User::iGet()->getLang();
        $datas['xmllang'] = User::iGet()->getXMLLang();
        $datas['htmllang'] = substr($datas['lang'], 0, 2);

        $this->_view->addBlock('head', 'head', $this->_view->get('head', $datas, null, $noCacheDatas));
        $datas['login'] = true;
        $this->_view->addBlock('js', 'footer', $this->_view->get('footer', $datas, null, $noCacheDatas));
        if(isset($noCacheDatas['back']))
        { // we need to take off the back url else it will be overriden by the global index page cache
            $back = $noCacheDatas['back'];
            unset($noCacheDatas['back']);
        }
        $this->_view->addBlock('login', 'contents', $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas));
        $noCacheDatas['token'] = User::iGet()->getToken();
        if(isset($back)) // bring it back here for the global page
            $noCacheDatas['back'] = $back;

        return $this->_view->get('index', $datas, null, $noCacheDatas);
    }

    /**
     * Generates opensearch template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of opensearch template
     */
    public function opensearch(array $datas, array $noCacheDatas)
    {
        return $this->_view->get(__FUNCTION__, $datas);
    }

    /**
     * Generates opml template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of opml template
     */
    public function opml(array $datas, array $noCacheDatas)
    {
        $streams = $datas['streams'];
        unset($datas['streams']);

        foreach($streams as $stream)
        {
            if(!isset($datas['groups'][$stream['gid']]))
                $datas['groups'][$stream['gid']] = $stream['gname'];
            $datas['streams'][$stream['gid']][] = $stream;
        }

        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates upload iframe template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of upload template
     */
    public function upload(array $datas, array $noCacheDatas)
    {
        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates user template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of use template
     */
    public function user(array $datas, array $noCacheDatas)
    {
        $datas['lang'] = User::iGet()->getLang();
        $datas['xmllang'] = User::iGet()->getXMLLang();
        $datas['htmllang'] = substr($datas['lang'], 0, 2);
        $noCacheDatas['uid'] = User::iGet()->getUid();
        $noCacheDatas['ttl'] = Config::iGet()->get('defaultMinStreamRefreshTime') * 60 * 1000;
        $noCacheDatas['opensearch'] = isset($datas['opensearch']) ? $datas['opensearch'] : 0;
        $noCacheDatas['pagetitle'] = 'OpenWebReader - ' . $this->_view->_('User creation');

        $datas['themes'] = parent::getList();
        $datas['plugins'] = Plugins::getList();

        $this->_view->addBlock('head', 'head', $this->_view->get('head', $datas, null, $noCacheDatas));
        $this->_view->addBlock('user', 'contents', $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas));
        $this->_view->addBlock('footer', 'footer', $this->_view->get('footer', $datas, null, $noCacheDatas));

        $noCacheDatas['token'] = User::iGet()->getToken();

        return $this->_view->get('index', $datas, null, $noCacheDatas);
    }

    /**
     * Generates users block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of users block template
     */
    public function users(array $datas, array $noCacheDatas)
    {
        $datas['nbusers'] = count($datas['users']);
        $noCacheDatas['token'] = User::iGet()->getToken();
        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates rss template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of rss template
     */
    public function rss(array $datas, array $noCacheDatas)
    {
    }

    /**
     * Generates index template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of index template
     */
    public function index(array $datas, array $noCacheDatas)
    {
        $noCacheDatas['groups_select'] = $this->_view->get('categories_selects', array(
                                                            'gid' => 0,
                                                            'groups' => $datas['groups']));

        $noCacheDatas['unread_0'] = isset($datas['unreads'][0]) ? $datas['unreads'][0] : 0;
        $noCacheDatas['bold_0'] = $noCacheDatas['unread_0'] > 0 ? ' class="bold"' : '';
        $noCacheDatas['userlogin'] = htmlentities(User::iGet()->getLogin(), ENT_COMPAT, 'UTF-8');

        $datas['userrights'] = User::iGet()->getRights();
        $datas['maxuploadfilesize'] = Config::iGet()->get('maxUploadFileSize');

        $datas['lang'] = User::iGet()->getLang();
        $datas['surl'] = Config::iGet()->get('surl');
        $datas['xmllang'] = User::iGet()->getXMLLang();
        $datas['htmllang'] = substr($datas['lang'], 0, 2);
        $noCacheDatas['uid'] = User::iGet()->getUid();
        $noCacheDatas['ttl'] = Config::iGet()->get('defaultMinStreamRefreshTime') * 60 * 1000;
        $noCacheDatas['opensearch'] = isset($datas['opensearch']) ? $datas['opensearch'] : 0;
        $noCacheDatas['pagetitle'] = 'OpenWebReader';

        $this->_view->addBlock('head', 'head', $this->_view->get('head', $datas, null, $noCacheDatas));
        $this->_view->addBlock('board', 'header', $this->_view->get('board', $datas, null, $noCacheDatas));
        $this->_view->addBlock('menu', 'contents', $this->_view->get('menu', $datas, null, $noCacheDatas));
        $this->_view->addBlock('content', 'contents', $this->_view->get('content', $datas, null, $noCacheDatas));
        $this->_view->addBlock('footer', 'footer', $this->_view->get('footer', $datas, null, $noCacheDatas));

        $noCacheDatas['token'] = User::iGet()->getToken();

        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates tags block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of tags block template
     */
    public function tags(array $datas, array $noCacheDatas)
    {
        $block = '';
        foreach($datas['tags'] as $tag)
            $block .= self::tag($tag, $noCacheDatas);

        return $block;
    }

    /**
     * Generates post block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of post block template
     */
    public function post(array $datas, array $noCacheDatas)
    {
        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates tag block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of tag block template
     */
    public function tag(array $datas, array $noCacheDatas)
    {
        if(isset($datas['tags'])) $datas = array_merge($datas, $datas['tags'][0]);
        $noCacheDatas['unread'] = $datas['unread'];
        $noCacheDatas['bold'] = $datas['unread'] > 0 ? 'bold ' : '';
        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates posts block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of posts block template
     */
    public function posts(array $datas, array $noCacheDatas)
    {
        $block = '';

        if(empty($datas['search']) && !empty($datas['pager']))
            $block .= $this->_view->get('post_tools', $datas['pager']);

        unset($datas['news']['ids']);

        foreach($datas['news'] as $k => $new)
            $block .= self::post($new, $noCacheDatas);

        return $block;
    }


    /**
     * Generates stream_details template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of stream_details template
     */
    public function stream_details(array $datas, array $noCacheDatas)
    {
        unset($datas['title']);
        $datas['contents']['nextRefresh'] = Dates::format($datas['lastupd'] + $datas['ttl']);
        $datas['contents']['id'] = $datas['id'];
        $datas['contents']['url'] = $datas['url'];

        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates post_tags block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of post_tags block template
     */
    public function post_tags(array $datas, array $noCacheDatas)
    {
        $block = array();
        foreach($datas['tags'] as $tag)
            $block[] = $tag['name'];

        return join(', ', $block);
    }

    /**
     * Generates stream template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of stream template
     */
    public function stream(array $datas, array $noCacheDatas)
    {
        $noCacheDatas['bold'] = $noCacheDatas['unread'] > 0 ? 'bold ' : '';

        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates streams template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of streams template
     */
    public function streams(array $datas, array $noCacheDatas)
    {
        $streamsToDisplay = $groups_select = array();
        foreach($datas['streams'] as $stream)
        {
            if(!isset($groups_select[$stream['gid']]))
                $groups_select[$stream['gid']] = $this->_view->get('categories_selects', array(
                                                            'gid' => $stream['gid'],
                                                            'groups' => $datas['groups']));
            $streamsToDisplay[$stream['name']] = $stream;
        }

        ksort($streamsToDisplay);

        $block = '';

        foreach($streamsToDisplay as $stream)
        {
            $block .= self::stream($stream, array(
                    'groups_select'     => $groups_select[$stream['gid']],
                    'unread'            => $stream['unread']
                    ));
        }

        return $block;
    }

    /**
     * Generates categories block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of categories block template
     */
    public function categories(array $datas, array $noCacheDatas)
    {
        $block = '';
        foreach($datas['groups'] as $group)
        {
            $noCacheDatas['unread'] = $group['unread'];
            $block .= self::category($group, $noCacheDatas);
        }

        return $block;
    }

    /**
     * Generates category block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of category block template
     */
    public function category(array $datas, array $noCacheDatas)
    {
        $noCacheDatas['bold'] = $noCacheDatas['unread'] > 0 ? 'bold ' : '';
        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates post_details block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of post_details block template
     */
    public function post_details(array $datas, array $noCacheDatas)
    {
        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates post_content block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of post_content block template
     */
    public function post_content(array $datas, array $noCacheDatas)
    {
        if(User::iGet()->getConfig('blockimg'))
        {
            array_walk_recursive($datas, function(&$data) {
                $data = preg_replace('/<img\b([^>]*)(src\s*=\s*([\'"])?(.*?)\\3\s*)[^>]*\/?>/ise',
                    "'<a href=\"javascript:;\" title=\"Blocked image, click to see it ! " .
                    "('.addcslashes(\"\\4\", '\"').')\" class=\"img_blocked backgrounded\" " .
                    "onclick=\"rP.loadImage(this, \''.addcslashes(\"\\4\", '\'').'\');\">" .
                    "<img alt=\"&nbsp;&nbsp;&nbsp;&nbsp;\"/></a>'", $data);
            });
        }

        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates stats block template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of stats block template
     */
    public function stats(array $datas, array $noCacheDatas)
    {
        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates CLI logs template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of logs template
     */
    public function logs(array $datas, array $noCacheDatas)
    {
        return $this->_view->get(__FUNCTION__, $datas);
    }
}
