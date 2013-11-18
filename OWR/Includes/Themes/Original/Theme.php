<?php

namespace OWR\Includes\Themes\Original;
use OWR\Theme as pTheme, OWR\User, OWR\Config;

/**
 * Default theme
 *
 * @uses View the page renderer
 * @uses User the current user
 * @uses Config the config instance
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
        $noCacheDatas['token'] = User::iGet()->getToken();

        $this->_view->addBlock('head', 'head', $this->_view->get('head', $datas, null, $noCacheDatas));
        $datas['login'] = true;
        $this->_view->addBlock('js', 'footer', $this->_view->get('footer', $datas, null, $noCacheDatas));
        $this->_view->addBlock('login', 'contents', $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas));

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

    public function upload(array $datas, array $noCacheDatas)
    {
        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

    /**
     * Generates use template
     *
     * @param array $datas datas to generate template
     * @param array $noCacheDatas not cached datas to generate template
     * @access public
     * @return string generated content of use template
     */
    public function user(array $datas, array $noCacheDatas)
    {
        $noCacheDatas['token'] = User::iGet()->getToken();
        $datas['lang'] = User::iGet()->getLang();
        $datas['xmllang'] = User::iGet()->getXMLLang();
        $datas['htmllang'] = substr($datas['lang'], 0, 2);
        $noCacheDatas['uid'] = User::iGet()->getUid();
        $noCacheDatas['ttl'] = Config::iGet()->get('defaultMinStreamRefreshTime') * 60 * 1000;
        $noCacheDatas['opensearch'] = isset($datas['opensearch']) ? $datas['opensearch'] : 0;
        $noCacheDatas['pagetitle'] = 'OpenWebReader - ' . $this->_view->_('User creation');

        $datas['themes'] = array();
        $themes = new \DirectoryIterator(dirname(__DIR__));
        foreach($themes as $theme)
        {
            if(!$themes->isDot() && $theme->isDir())
                $datas['themes'][(string) $theme] = $this->_name === (string) $theme;
        }

        if(!empty($datas['themes']))
            ksort($datas['themes']);
        
        $this->_view->addBlock('head', 'head', $this->_view->get('head', $datas, null, $noCacheDatas));
        $this->_view->addBlock('user', 'contents', $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas));
        $this->_view->addBlock('footer', 'footer', $this->_view->get('footer', $datas, null, $noCacheDatas));
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
        $noCacheDatas['token'] = User::iGet()->getToken();
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

        if(empty($datas['search']))
            $block .= $this->_view->get('post_tools', $datas['pager']);

        unset($datas['news']['ids']);

        foreach($datas['news'] as $k => $new)
            $block .= self::post($new, $noCacheDatas);

        return $block;
    }
    
    public function stream_details(array $datas, array $noCacheDatas)
    {
        unset($datas['stream']['title']);
        $datas['stream']['contents']['nextRefresh'] = $this->_getDate($datas['stream']['lastupd'] + $datas['stream']['ttl']);
        $datas['stream']['contents']['id'] = $datas['stream']['id'];
        $datas['stream']['contents']['url'] = $datas['stream']['url'];

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

    public function stream(array $datas, array $noCacheDatas)
    {
        $unread = $datas['unread'];
        unset($datas['unread']);
        $noCacheDatas['unread'] = $unread;
        $noCacheDatas['bold'] = $unread > 0 ? 'bold ' : '';

        return $this->_view->get(__FUNCTION__, $datas, null, $noCacheDatas);
    }

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
                    'groups_select'     => $groups_select[$stream['gid']]
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
            $block .= self::category($group, $noCacheDatas);

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
        $noCacheDatas['unread'] = $datas['unread'];
        $noCacheDatas['bold'] = $datas['unread'] > 0 ? 'bold ' : '';
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
}
