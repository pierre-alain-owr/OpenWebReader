<?php
/**
 * Controller class
 * Get the request, clean it and execute the given action
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
 * @subpackage Rest
 */
namespace OWR\REST;
use OWR\Controller as C,
    OWR\Error,
    OWR\Exception,
    OWR\Config,
    OWR\Logs,
    OWR\DB,
    OWR\User,
    OWR\cURLWrapper,
    OWR\DB\Request as DBRequest,
    OWR\Stream\Parser,
    OWR\View,
    OWR\DAO,
    OWR\XML,
    OWR\Logic\Response as LogicResponse,
    OWR\Cron,
    OWR\Logic;
if(!defined('INC_CONFIG')) die('Please include config file');
/**
 * This object is the front door of the application
 * @uses OWR\DAO deals with database
 * @uses OWR\Config the config instance
 * @uses OWR\Singleton implements the singleton pattern
 * @uses OWR\DB the database link
 * @uses OWR\View the page renderer
 * @uses OWR\Session session managing
 * @uses OWR\Rest\Request the request to execute
 * @uses OWR\User the current user
 * @uses OWR\Exception the exceptions handler
 * @uses OWR\Error the errors handler
 * @uses OWR\DBRequest a request sent to database
 * @uses OWR\Log the logs/errors storing object
 * @uses OWR\XML serialize/unserialize XML datas
 * @uses OWR\Cron adds/modify crontab
 * @uses OWR\Logic the main Logic object
 * @package OWR
 * @subpackage Rest
 */
class Controller extends C
{
    /**
     * Constructor, sets : all needed instances, errors and exceptions handler,
     *
     * @access protected
     */
    protected function __construct()
    {
        $this->_cfg = Config::iGet();

        // secure only ?
        if($this->_cfg->get('httpsecure') && (!isset($_SERVER['HTTPS']) || 'on' !== $_SERVER['HTTPS']))
        {
            header('Location: https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
            exit;
        }

        set_error_handler(array('OWR\Error', 'error_handler')); // errors
        set_exception_handler(array('OWR\Exception', 'exception_handler')); // exceptions not catched
        error_reporting(DEBUG ? -1 :    E_CORE_ERROR |
                                        E_COMPILE_ERROR |
                                        E_ERROR |
                                        E_PARSE |
                                        E_USER_ERROR |
                                        E_USER_WARNING |
                                        E_USER_NOTICE |
                                        E_USER_DEPRECATED);

        try
        {
            $this->_db = DB::iGet();
        }
        catch(Exception $e)
        {
            throw new Exception($e->getContent(), 503);
        }
        $this->_user = User::iGet();
    }

    /**
     * Executes the given action
     * This method only accepts a RestRequest object
     * It will try to log the user in, and execute the action
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed Request the request to execute
     * @access public
     */
    public function execute(Request $request)
    {
        try
        {
            $this->_request = $request;
            $this->_request->begintime = microtime(true);

            $method = $this->_request->getMethod();

            if(!User::iGet()->isLogged())
            {
                if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))
                { // HTTP Basic
                    if(!User::iGet()->auth($_SERVER['PHP_AUTH_USER'], md5($_SERVER['PHP_AUTH_USER'].$_SERVER['PHP_AUTH_PW'])))
                    {
                        View::iGet()->addHeaders(array('WWW-Authenticate' => 'Basic realm="OpenWebReader"'));
                        throw new Exception('Authentification required', 401);
                    }
                }
                elseif((empty($_COOKIE['auth']) &&
                    ($method !== 'post' || $this->_request->do !== 'login' || empty($this->_request->tlogin) ||
                    empty($this->_request->key) || empty($this->_request->uid) ||
                    !$this->_user->checkToken(true, $this->_request->uid, $this->_request->tlogin, $this->_request->key, 'restauth'))) ||

                    (!empty($_COOKIE['auth']) && ($data = @unserialize(base64_decode($_COOKIE['auth'], true))) &&
                    !empty($data['tlogin']) && !empty($data['key']) && !empty($data['uid']) &&
                    !$this->_user->checkToken(true, $data['uid'], $data['tlogin'], $data['key'], 'restauth')))
                { // COOKIE, not stateless
                    throw new Exception('Authentification required', 401);
                }
            }

            if('post' === $method && 'login' === $this->_request->do)
            {
                $datas['tlogin'] = $this->_request->tlogin;
                $datas['key'] = $this->_request->key;
                $datas['uid'] = $this->_user->getUid();
                setcookie('auth', base64_encode(serialize($datas)), $this->_cfg->get('sessionLifeTime'),
                    $this->_cfg->get('path'), $this->_cfg->get('url'), $this->_cfg->get('httpsecure'), true);
                unset($datas);
                return $this;
            }

            switch($method)
            {
                case 'get':
                    $authorized = array(
                        'do_getlastnews'        => true,
                        'do_getlivenews'        => true,
                        'do_getmenupartgroup'   => true,
                        'do_getmenupartstream'  => true,
                        'do_getnewcontents'     => true,
                        'do_getnewdetails'      => true,
                        'do_getopml'            => true,
                        'do_getrss'             => true,
                        'do_getstream'          => true,
                        'do_getunread'          => true,
                        'do_getusers'           => true,
                        'do_maintenance'        => true,
                        'do_search'             => true,
                        'do_regenerateopmltoken'=> true,
                        'do_regeneratersstoken' => true,
                        'do_logout'             => true,
                        'do_index'              => true,
                        'do_get'                => true
                    );
                    break;

                case 'post':
                    $authorized = array(
                        'do_editopml'           => true,
                        'do_editstream'         => true,
                        'do_editstreamgroup'    => true,
                        'do_edituser'           => true
                    );
                    break;

                case 'put':
                    $authorized = array(
                        'do_upnew'              => true,
                        'do_rename'             => true,
                        'do_move'               => true,
                        'do_edituser'           => true
                    );
                    break;

                case 'delete':
                    $authorized = array(
                        'do_delete'=>true,
                        'do_clearstream'=>true
                    );
                    break;

                default: throw new Exception('Method not supported', 405); break;
            }

            $action = 'do_'.$this->_request->do;

            if(!method_exists($this, $action))
            {
                throw new Exception('Bad request ', 400);
            }

            if(!isset($authorized[$action]))
            {
                throw new Exception('Not implemented', 501);
            }

            $this->$action();
        }
        catch(Exception $e)
        {
            $this->_db->rollback();
            $status = $e->getCode();
            switch($status)
            {
                case Exception::E_OWR_WARNING:
                case Exception::E_OWR_DIE:
                    $status = 500;
                    break;

                case Exception::E_OWR_NOTICE:
                    $status = 200;
                    break;

                default:
                    break;
            }
            throw new Exception($e->getContent(), $status);
        }

        return $this;
    }

    /**
     * Process the response of a Logic call
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param mixed LogicResponse the response of the
     */
    public function processResponse(LogicResponse $response = null)
    {
        $status = $response->getStatus();
        if($status)
            View::iGet()->setStatusCode($status);

        switch($response->getNext())
        {
            case 'error': // error
                $this->_request->page = array(
                    'error'  => $response->getError(),
                    'errors' => $response->getErrors()
                );
                $ret = false;
                break;

            case 'redirect': // redirection
                $location = $response->getLocation();
                if('login' === $location)
                {
                    View::iGet()->setStatusCode(401);
                }
                elseif('logout' === $location)
                {
                    $this->do_logout(false);
                    $location = 'login';
                }

                if(!empty($location))
                    $this->redirect($location);

            case 'ok': // ok !
                $ret = true;
                $datas = $response->getDatas();

                if(201 === $status)
                { // created
                    if(!empty($datas['id']))
                    {
                        View::iGet()->addHeaders(array('Location' => Config::iGet()->get('surl').'rest/get/'.$datas['id']));
                    }
                }

                $this->_request->page = $datas;
                break;

            default:
                throw new Exception('Invalid return from Logic', Exception::E_OWR_DIE);
                break;
        }

        return $ret;
    }

    /**
     * Render the page
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $contents the page contents
     * @return boolean true on success
     * @access public
     */
    public function renderPage($statusCode = 200)
    {
        $error = @ob_get_clean();
        if($error)
        {
            do
            {
                Logs::iGet()->log($error);
            }
            while($error = @ob_get_clean());
        }

        isset($this->_view) || $this->_view  = View::iGet();
        $page = array();

        if(!isset($this->_request->unreads))
        {
            try
            {
                $this->do_getunread(true);
            }
            catch(Exception $e)
            {
                Logs::iGet()->log($e->getContent(), $e->getCode());
            }
        }

        $page['unreads'] =& $this->_request->unreads;
        $page['contents'] =& $this->_request->page;

        if(Logs::iGet()->hasLogs())
        {
            if(DEBUG || $this->_user->isAdmin())
            {
                $page['errors'] = Logs::iGet()->getLogs();
                $this->_cleanIndent($page['errors']);
            }
            else
            {
                Logs::iGet()->writeLogs();
                $page['errors'] = (array)'Some errors occured.';
            }
        }
        elseif(isset($_SERVER['REQUEST_METHOD']) && 'GET' === $_SERVER['REQUEST_METHOD'])
        {
            $etag = '"owr-'.md5(serialize($page)).'"';
            $this->_view->addHeaders(array(
                'Cache-Control' => 'Public, must-revalidate',
                "Expires" => gmdate("D, d M Y H:i:s", $this->_request->begintime + $this->_cfg->get('cacheTime'))." GMT",
                'Etag' => $etag
            ), true);
            if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
            {
                $this->_view->setStatusCode(304, true);
                flush();
                return true;
            }
        }

        $now = microtime(true);
        $page['executionTime'] = round($now - $this->_cfg->get('begintime'), 6);
        $page['requestTime'] = round($now - $this->_request->begintime, 6);
        $page['sqlTime'] = round(DB::getTime(), 6);

        $this->_view->setStatusCode($statusCode, true);

        $httpAccept = $this->_request->getHTTPAccept();

        if('json' === $httpAccept)
        {
            $this->_view->addHeaders(array('Content-Type' => 'application/json; charset=utf-8'));
            $page = json_encode($page);
        }
        elseif('xml' === $httpAccept)
        {
            $this->_view->addHeaders(array('Content-Type' => 'text/xml; charset=utf-8'));
            $page = XML::serialize($page, 'rsp', $statusCode);
        }
        elseif('html' === $httpAccept)
        {
            $this->_view->addHeaders(array('Content-Type' => 'text/html; charset=utf-8'));
        }

        $this->_view->render($page);

        return true;
    }

    /**
     * Gets a template to display
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $tpl the name of the tpl, without the extension
     * @param array $datas the datas to apply to the template
     * @param boolean $return returns the template instead of rendering it
     * @return mixed the template if $return=true, else true
     * @access protected
     */
    protected function _getPage($tpl, array $datas = array(), $return = false)
    {
        if(!isset($this->_view))
        {
            $this->_view = View::iGet();
        }

        if('text/html' === $this->_request->getHTTPAccept())
        {
            if($return) return parent::_getPage($tpl, $datas, $return);
            else $this->_request->page = parent::_getPage($tpl, $datas, $return);
            return;
        }

        $xml = $empty = false;
        $page = array();

        switch($tpl)
        {
            case 'new_contents':
                $request = new Request($datas);
                Logic::getCachedLogic('news')->view($request);
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $page = $response->getDatas();
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                }
                unset($response, $request);
            break;

            case 'new_details':
                $page['details'] = array();
                $request = new Request($datas);
                Logic::getCachedLogic('news')->view($request);
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $data = $response->getDatas();
                    $page['url'] = htmlspecialchars($data['link'], ENT_COMPAT, 'UTF-8');
                    $page['title'] = htmlspecialchars($data['title'], ENT_COMPAT, 'UTF-8');
                    foreach($data['contents'] as $k => $content)
                    {
                        switch($k)
                        {
                            case 'description':
                            case 'content':
                            case 'encoded':
                            case 'url':
                            case 'title':
                                break;

                            default:
                                $page['details'][$k] = $content;
                                break;
                        }
                    }
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                }
                unset($response, $request);
            break;

            case 'menu_part_category':
                $page['gname'] = $datas['name'];
                $page['groupid'] = $datas['gid'];

                if(empty($this->_request->unreads))
                    $this->do_getunread(true);

                $page['unread'] = isset($this->_request->unreads[$datas['gid']]) ? $this->_request->unreads[$datas['gid']] : 0;
                $tpl = 'menu_contents';
                break;

            case 'menu_part_group':
                $streams = DAO::getDAO('streams_relations')->get(array('gid' => $datas['id']), 'rssid');
                if(!$streams)
                {
                    $empty = true;
                    break;
                }
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);
                if(is_object($streams))
                    $streams = array($streams);
                $request = new Request(array('id'=>null));
                Logic::getCachedLogic('streams_groups')->view($request);
                $response = $request->getResponse();
                $groups = array();
                if('error' !== $response->getNext())
                {
                    $g = $response->getDatas();
                    if(!$response->isMultiple()) $g = array($g);
                    foreach($g as $k=>$group)
                    {
                        $groups[$group['id']] = $group['name'];
                    }
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                    break;
                }
                unset($response, $g);

                foreach($streams as $s)
                {
                    $request->id = $s->rssid;
                    Logic::getCachedLogic('streams')->view($request);
                    $response = $request->getResponse();
                    if('error' !== $response->getNext())
                    {
                        $stream = $response->getDatas();
                        $stream['groups'] = $groups;
                        if($stream['status'] > 0)
                        {
                            $stream['unavailable'] = $this->_getDate($stream['status']);
                        }
                        $unread = isset($this->_request->unreads[$stream['id']]) ? $this->_request->unreads[$stream['id']] : 0;
                        $page['streams'][] = $stream;
                    }
                    else
                    {
                        Logs::iGet()->log($response->getError(), $response->getStatus());
                        $empty = true;
                    }
                    unset($response);
                }
                unset($streams, $request);
                break;

            case 'menu_part_stream':
                $request = new Request($datas);
                Logic::getCachedLogic('streams')->view($request);
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $page['stream'] = $response->getDatas();
                    $page['stream']['nextRefresh'] = $this->_getDate($this->_request->begintime + $page['stream']['ttl']);
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                }
                unset($response, $request);
                break;

            case 'news':
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);

                $ids = null;
                $uid = $this->_user->getUid();

                if(!empty($datas['sort']))
                {
                    $order = $datas['sort'].' '.$datas['dir'];
                    if('news.pubDate' !== $datas['sort'])
                        $order .= ',news.pubDate DESC';
                }
                else
                {
                    $order = 'news.pubDate DESC';
                }

                $offset = 0;

                if(isset($datas['offset']))
                {
                    $page['offset'] = (int)$datas['offset'];

                    if($datas['offset'] > 0)
                    {
                        $offset = (int)($datas['offset']*10);
                    }
                }
                else
                {
                    $page['offset'] = 0;
                }

                $offset = $offset.',10'; // TODO : change this limit by the user defined one

                if(isset($datas['id']) && is_array($datas['id']))
                {
                    if(empty($datas['id']))
                    {
                        $empty = true;
                        break;
                    }
                    $request = new Request(array('ids' => $datas['id']));
                    Logic::getCachedLogic('news')->view($request, array(), $order, 'news.id', $offset);
                    $page['nbNews'] = count($datas['id']);
                }
                elseif(empty($datas['id']))
                {
                    $request = new Request(array('id' => null));
                    Logic::getCachedLogic('news')->view($request, array('status' => 1), $order, 'news.id', $offset);
                    $page['nbNews'] = $this->_request->unreads[0];
                }
                else
                {
                    try
                    {
                        $table = DAO::getType($datas['id']);
                    }
                    catch(Exception $e)
                    {
                        switch($e->getCode())
                        {
                            case Exception::E_OWR_NOTICE:
                            case Exception::E_OWR_WARNING:
                                Logs::iGet()->log($e->getContent(), $e->getCode());
                                $empty = true;
                                break 2;
                            default: throw new Exception($e->getContent(), $e->getCode());
                                break;
                        }
                    }

                    $request = new Request(array('id' => null));
                    if('streams' === $table)
                    {
                        Logic::getCachedLogic('news')->view($request, array('rssid' => $datas['id']), $order, 'news.id', $offset);
                        $nb = DAO::getCachedDAO('news_relations')->count(array('rssid' => $datas['id']), 'newsid');
                        $page['nbNews'] = $nb ? $nb->nb : 0;
                    }
                    elseif('streams_groups' === $table)
                    {
                        Logic::getCachedLogic('news')->view($request, array('gid' => $datas['id']), $order, 'news.id', $offset);
                        $nb = DAO::getCachedDAO('news_relations')->count(array('streams_relations.gid' => $datas['id']), 'newsid');
                        $page['nbNews'] = $nb ? $nb->nb : 0;
                    }
                    else
                    {
                        Logs::iGet()->log('Invalid id');
                        $empty = true;
                        break;
                    }
                }

                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $news = $response->getDatas();
                    if(empty($news))
                    {
                        $empty = true;
                        break;
                    }

                    if(!$response->isMultiple()) $news = array($news);
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                    break;
                }
                unset($response, $request, $result);

                $page['sort'] = !empty($datas['sort']) ? $datas['sort'] : null;
                $page['dir'] = !empty($datas['dir']) ? $datas['dir'] : null;

                foreach($news as $new)
                {
                    if(isset($datas['searchResults'][$new['id']]))
                        $new['search_result'] = (float) $datas['searchResults'][$new['id']];
                    $new['pubDate'] = $this->_getDate($new['pubDate']);
                    $page['news'][] = $new;
                }
                unset($news);
            break;

            case 'index':
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);

                $groups = $this->_getPage('menu', $datas, true);
                $page['groups'] = $groups['groups'];
                unset($groups);

                $news = $this->_getPage('news', $datas, true);
                $page['news'] = $news['news'];
                unset($news);
                $page['ttl'] = $this->_cfg->get('defaultMinStreamRefreshTime');
                $page['lang'] = $this->_user->getLang();
                $page['sort'] = $datas['sort'];
                $page['dir'] = $datas['dir'];
                $page['uid'] = $this->_user->getUid();
                break;

            case 'opml':
                $page = parent::_getPage('opml', array('dateCreated'=>date("D, d M Y H:i:s T")), true);
                break;

            case 'edituser':
                if(empty($datas['id']))
                {
                    $page['contents']['surl'] = $this->_cfg->get('surl');
                    $page['contents']['timezones'] = $this->_user->getTimeZones();
                    $page['contents']['userrights'] = $this->_user->getRights();
                    break;
                }

                $request = new Request($datas);
                $page['surl'] = $this->_cfg->get('surl');
                $page['timezones'] = $this->_user->getTimeZones();
                $page['userrights'] = $this->_user->getRights();
                $cacheTime = 0;
                Logic::getCachedLogic('users')->view($request);
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $page['user'] = $response->getDatas();
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                }
                unset($response, $request);
            break;

            case 'users':
                $request = new Request($datas);
                $cacheTime = 0;
                $datas['surl'] = $this->_cfg->get('surl');
                Logic::getCachedLogic('users')->view($request, array(), 'login');
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    if(!$response->isMultiple())
                    {
                        $page['users'][] = $response->getDatas();
                        $page['nbusers'] = 1;
                    }
                    else
                    {
                        $page['users'] = $response->getDatas();
                        $page['nbusers'] = count($page['users']);
                    }
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                }
                unset($response, $request);
            break;

            case 'rss':
                $page = parent::_getPage('rss', $datas, true);
            break;

            case 'login':
                $page['surl'] = $this->_cfg->get('surl');
                $page['xmlLang'] = $this->_user->getXMLLang();
                $page['back'] = $this->_request->back;
            default: break;
        }

        if(!empty($page))
        {
            if($return)
                return $page;
            else
                $this->_request->page = $page;
        }
    }

    /**
     * Logout the user
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $redirect redirects the user to the login page
     * @access protected
     * @return $this
     */
    protected function do_logout($redirect=true)
    {
        if(isset($_COOKIE['auth']))
        {
            setcookie('auth', '', $this->_request->begintime - 42000, $this->_cfg->get('path'), $this->_cfg->get('url'), $this->_cfg->get('httpsecure'), true);
        }

        $this->redirect('login');

        return $this;
    }

    /**
     * Renders the template relative to the id
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_get()
    {
        if(!$this->_request->id)
            throw new Exception('Missing id', Exception::E_OWR_BAD_REQUEST);

        $type = DAO::getType($this->_request->id);

        switch($type)
        {
            case 'streams':
            case 'news':
            case 'streams_groups':
            case 'users':
                break;

            default:
                throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
                break;
        }

        $order = $offset = null;

        if(!empty($this->_request->order))
        {
            $order = $request->order.' '.$request->dir;
        }

        if(!empty($this->_request->offset))
        {
            $offset = ($request->offset * 10).',10';
        }

        $this->_request->page = Logic::getCachedLogic($type)->view($this->_request, array(), $order, '', $offset);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }
}