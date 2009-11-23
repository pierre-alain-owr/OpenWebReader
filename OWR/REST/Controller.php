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
    OWR\Error as Error, 
    OWR\Exception as Exception, 
    OWR\Config as Config,
    OWR\Logs as Logs,
    OWR\DB as DB,
    OWR\User as User,
    OWR\cURLWrapper as cURLWrapper,
    OWR\DB\Request as DBRequest,
    OWR\Stream\Parser as Parser,
    OWR\View as View,
    OWR\DAO as DAO,
    OWR\XML as XML,
    OWR\Logic\Response as LogicResponse;
if(!defined('INC_CONFIG')) die('Please include config file');
/**
 * This object is the front door of the application
 * @uses DAO deals with database
 * @uses Config the config instance
 * @uses Singleton implements the singleton pattern
 * @uses DB the database link
 * @uses View the page renderer
 * @uses Session session managing
 * @uses Rest\Request the request to execute
 * @uses User the current user
 * @uses Exception the exceptions handler
 * @uses Error the errors handler
 * @uses DBRequest a request sent to database
 * @uses Log the logs/errors storing object
 * @uses XML serialize/unserialize XML datas
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
                    ($method !== 'post' || $this->_request->do !== 'login' || empty($this->_request->tlogin) || empty($this->_request->key) || empty($this->_request->uid) ||
                    !$this->_user->checkToken(true, $this->_request->uid, $this->_request->tlogin, $this->_request->key, 'restauth'))) ||
    
                    (!empty($_COOKIE['auth']) && ($data = @unserialize(base64_decode($_COOKIE['auth'], true))) && !empty($data['tlogin']) && !empty($data['key']) && !empty($data['uid']) &&
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
                setcookie('auth', base64_encode(serialize($datas)), $this->_cfg->get('sessionLifeTime'), $this->_cfg->get('path'), $this->_cfg->get('url'), $this->_cfg->get('httpsecure'), true);
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
                    $authorized = array('do_delete'=>true, 'do_clearstream'=>true);
                    break;
                
                default: throw new Exception('Method not supported', 405); break;
            }

            $action = 'do_'.$this->_request->do;

            if(!method_exists($this, $action))
            {
                throw new Exception('Bad request', 400);
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
            throw new Exception($e->getContent(), $e->getCode());
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
                {
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
            $this->_request->page = parent::_getPage($tpl, $datas, $return);
            return;
        }

        $xml = $empty = false;
        $page = array();
        
        switch($tpl)
        {
            case 'new':
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);
                    
                if(isset($this->_request->offset))
                    $offset = (int)(($this->_request->offset*100) + 99);
                else $offset = 0;
                
                $query = "
    SELECT DISTINCT(n.id), rel.rssid rssid, title, link, status live, rrn.name, n.pubDate, rg.name gname, r.favicon
        FROM news n
        JOIN streams r ON (n.rssid=r.id)
        JOIN news_relations rel ON (n.id=rel.newsid)
        JOIN streams_relations_name rrn ON (rrn.rssid=n.rssid AND rrn.uid=rel.uid)
        JOIN streams_relations rr ON (rr.rssid=rrn.rssid AND rr.uid=rel.uid)
        JOIN streams_groups rg ON (rg.id=rr.gid AND rg.uid=rel.uid)
        WHERE rel.uid=".$this->_user->getUid()." AND status=1
        ORDER BY n.pubDate DESC, n.lastupd DESC
        LIMIT {$offset},1";
                    
                $news = $this->_db->get($query);
                if(!$news)
                {
                    $empty = true;
                    break;
                }

                while($new = $news->fetch(\PDO::FETCH_ASSOC))
                {
                    $new['pubDate'] = $this->_getDate($new['pubDate']);
                    $page['news'][] = $new;
                }
                
                $news->closeCursor();
                unset($news, $new);
                $page['nbNews'] = $this->_request->unreads[0];
            break;

            case 'new_contents':
                $query = "
    SELECT DISTINCT(n.id), rrn.name, n.title
        FROM news n
        JOIN news_relations rel ON (n.id=rel.newsid)
        JOIN streams_relations_name rrn ON (rrn.rssid=n.rssid)
        WHERE rel.uid=".$this->_user->getUid()." AND n.id=".$datas['id']."
        ORDER BY n.pubDate DESC, n.lastupd DESC";
                
                $new = $this->_db->getRow($query);
                if(!$new->next())
                {
                    $empty = true;
                    break;
                }

                $query = '
    SELECT contents
        FROM news_contents
        WHERE id='.$new->id;
                $contents = $this->_db->cGetOne($query);
                $new->contents = (array) ($contents->next() ? unserialize($contents->contents) : '');
                unset($contents);
                $page['contents'] = $new->asArray();
                unset($new);
            break;

            case 'new_details':
                $page['details'] = array();
                $query = '
    SELECT contents
        FROM news_contents
        WHERE id='.(int)$datas['id'];
                $contents = $this->_db->cGetOne($query);
                if(!$contents->next()) break;
                $page['details'] = (array) unserialize($contents->contents);
                unset($contents);
                $page['url'] = urlencode($page['details']['link']['contents']);
                $page['title'] = urlencode($page['details']['title']['contents']);
                $page['details']['title'] = $page['details']['link'] = $page['details']['pubDate'] = $page['details']['guid'] = $page['details']['url'] = $page['details']['description'] = $page['details']['author'] = $page['details']['encoded'] = null;
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
                $query = '
    SELECT DISTINCT(r.id), rr.gid AS groupid, r.url, rc.contents, r.lastupd, r.ttl, rrn.name, r.favicon, r.status
        FROM streams r
        JOIN streams_contents rc ON (r.id=rc.rssid)
        JOIN streams_relations rr ON (rr.rssid=r.id)
        JOIN streams_relations_name rrn ON (rrn.rssid=rr.rssid AND rrn.uid=rr.uid)
        WHERE rr.uid='.$this->_user->getUid().' AND rr.gid='.(int)$datas['id'].'
        ORDER BY name';
                $streams = $this->_db->getAll($query);
                if(!$streams->count())
                {
                    $empty = true;
                    break;
                }
                
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);

                $query = '
    SELECT name, id
        FROM streams_groups
        WHERE uid='.$this->_user->getUid().'
        ORDER BY name';
                
                $groups = $this->_db->getAll($query);
                if(!$groups->count())
                {
                    $empty = true;
                    break;
                }

                $streams->groups = array();
                while($groups->next())
                {
                    $streams->groups->{$groups->id} = $groups->name;
                }
                unset($groups);
                
                $streams->groups = $streams->groups->asArray(); // force array

                while($streams->next())
                {
                    $streams->contents = (array) unserialize($streams->contents);
                    $streams->unread = (isset($this->_request->unreads[$streams->id]) ? $this->_request->unreads[$streams->id] : 0);
                    if($streams->status > 0) 
                    {
                        $streams->unavailable = $this->_getDate($streams->status);
                    }
                    $page['groups'][] = $streams->asArray();
                }
                unset($streams);

                break;
                
            case 'menu_part_stream':
                $query = '
    SELECT DISTINCT(r.id), r.url, rc.contents, r.ttl
        FROM streams r
        JOIN streams_contents rc ON (r.id=rc.rssid)
        JOIN streams_relations rr ON (rr.rssid=r.id)
        WHERE rr.uid='.$this->_user->getUid().' AND r.id='.(int)$datas['id'];
                $stream = $this->_db->getRow($query);
                if(!$stream->next())
                {
                    break;
                }

                $page['stream'] = $stream->asArray();
                $page['stream']['contents'] = (array) unserialize($page['stream']['contents']);
                unset($stream);
                break;
                
            case 'news':
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);

                $cache = false;
                
                $ids = null;

                $order = !empty($datas['sort']) ? $datas['sort'].' '.$datas['dir'] : 'n.pubDate DESC, n.lastupd DESC';

                if(isset($datas['id']) && is_array($datas['id']))
                {
                    if(empty($datas['id']))
                    {
                        $empty = true;
                        break;
                    }
                    array_walk($datas['id'], 'intval');
                    
                    $query = '
    SELECT n.id, n.rssid, n.title, n.link, n.pubDate, n.author, nr.status live, nr.rssid
        FROM news_relations nr
        JOIN news n ON (nr.newsid=n.id)
        WHERE uid='.$this->_user->getUid().' AND n.id IN ('.join(',', $datas['id']).')
        ORDER BY '.$order;

                    if(isset($datas['offset']))
                    {
                        $page['offset'] = (int)$datas['offset'];
                        
                        if($page['offset'] > 0)
                        {
                            $offset = (int)($page['offset']*10);
                            $query .= "
                        LIMIT {$offset},10";
                        }
                        else
                        {
                            $query .= '
                        LIMIT 10';
                        }
                    }
                    else
                    {
                        $query .= '
                        LIMIT 10';
                    }

                    $page['nbNews'] = count($datas['id']);
                }
                elseif(!isset($datas['id']) || !$datas['id'])
                {
                    $query = '
    SELECT n.id, n.rssid, n.title, n.link, n.pubDate, n.author, nr.status live
        FROM news_relations nr
        JOIN news n ON (nr.newsid=n.id)
        WHERE uid='.$this->_user->getUid().' AND status=1
        ORDER BY '.$order;

                    if(isset($datas['offset']))
                    {
                        $page['offset'] = (int)$datas['offset'];
                        
                        if($page['offset'] > 0)
                        {
                            $offset = (int)($page['offset']*10);
                            $query .= "
                        LIMIT {$offset},10";
                        }
                        else
                        {
                            $query .= '
                        LIMIT 10';
                        }
                    }
                    else
                    {
                        $query .= '
                        LIMIT 10';
                    }

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
                    
                    if('streams' === $table)
                    {
                        $query = '
    SELECT n.id, n.rssid, n.title, n.link, n.pubDate, n.author, nr.status live
        FROM news_relations nr
        JOIN news n ON (nr.newsid=n.id)
        WHERE nr.uid='.$this->_user->getUid().' AND nr.rssid='.(int)$datas['id'].'
        ORDER BY '.$order;
    
                        if(isset($datas['offset']))
                        {
                            $page['offset'] = (int)$datas['offset'];
                            
                            if($page['offset'] > 0)
                            {
                                $offset = (int)($page['offset']*10);
                                $query .= "
                            LIMIT {$offset},10";
                            }
                            else
                            {
                                $query .= '
                            LIMIT 10';
                            }
                        }
                        else
                        {
                            $query .= '
                            LIMIT 10';
                        }
    
                        $nbNews = $this->_db->getOne('
    SELECT COUNT(newsid) AS nb
        FROM news_relations
        WHERE uid='.$this->_user->getUid().' AND rssid='.(int)$datas['id']);
                        $page['nbNews'] = $nbNews->next() ? $nbNews->nb : 0;
                    }
                    elseif('streams_groups' === $table)
                    {
                        $query = '
    SELECT n.id, n.rssid, n.title, n.link, n.pubDate, n.author, nr.status live
        FROM news_relations nr
        JOIN news n ON (nr.newsid=n.id)
        JOIN streams_relations sr ON (sr.rssid=n.rssid AND sr.uid='.$this->_user->getUid().')
        JOIN streams_groups rg ON (sr.gid=rg.id AND sr.uid='.$this->_user->getUid().')
        WHERE nr.uid='.$this->_user->getUid().' AND rg.id='.(int)$datas['id'].'
        ORDER BY '.$order;
    
                        if(isset($datas['offset']))
                        {
                            $page['offset'] = (int)$datas['offset'];
                            
                            if($page['offset'] > 0)
                            {
                                $offset = (int)($page['offset']*10);
                                $query .= "
                            LIMIT {$offset},10";
                            }
                            else
                            {
                                $query .= '
                            LIMIT 10';
                            }
                        }
                        else
                        {
                            $query .= '
                            LIMIT 10';
                        }
    
                        $nbNews = $this->_db->getOne('
    SELECT COUNT(DISTINCT(n.newsid)) AS nb
        FROM news_relations n
        JOIN streams_relations s ON (n.rssid=s.rssid AND s.uid=n.uid)
        WHERE n.uid='.$this->_user->getUid().' AND s.gid='.(int)$datas['id']);
                        $page['nbNews'] = $nbNews->next() ? $nbNews->nb : 0;
                    }
                    else
                    {
                        Logs::iGet()->log('Invalid id');
                        $empty = true;
                        break;
                    }
                }

                $ids = $this->_db->execute($query);
                if(!$ids->count())
                {
                    $empty = true;
                    break;
                }

                $streams = $groups = array();

                // get the related streams and groups
                while($ids->next())
                {
                    if(!isset($streams[$ids->rssid]))
                    {
                        $query = '
    SELECT s.favicon, srn.name, sr.gid
        FROM streams s
        JOIN streams_relations sr ON (s.id=sr.rssid AND sr.uid='.$this->_user->getUid().')
        JOIN streams_relations_name srn ON (s.id=srn.rssid AND srn.uid='.$this->_user->getUid().')
        WHERE s.id='.$ids->rssid;
                        $stream = $this->_db->execute($query);
                        if($stream->next())
                        {
                            $streams[$ids->rssid] = $stream;
                            if(!isset($groups[$stream->gid]))
                            {
                                $query = '
    SELECT name AS gname
        FROM streams_groups
        WHERE id='.$stream->gid.' AND uid='.$this->_user->getUid();
                                $group = $this->_db->execute($query);
                                $group->next();
                                $groups[$stream->gid] = $group;
                            }
                        }
                        else
                        {
                            Logs::iGet()->log("Can't get related streams/groups");
                            break;
                        }
                    }
                    $ids->name = $streams[$ids->rssid]->name;
                    $ids->favicon = $streams[$ids->rssid]->favicon;
                    $ids->gname = $groups[$streams[$ids->rssid]->gid]->gname;
                    $ids->gid = $streams[$ids->rssid]->gid;
                    if(isset($datas['searchResults'][$ids->id]))
                        $ids->search_result = (float)$datas['searchResults'][$ids->id];
                    $ids->pubDate = $this->_getDate($ids->pubDate);
                    $page['news'][] = $ids->asArray();
                }
                
                unset($ids, $groups, $streams);
            break;

            case 'menu':
                $query = '
    SELECT name AS gname, id AS groupid
        FROM streams_groups
        WHERE uid='.$this->_user->getUid().'
        ORDER BY gname';
                
                $groups = $this->_db->getAll($query);
                if(!$groups->count())
                {
                    $empty = true;
                    break;
                }
                
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);
                
                while($groups->next())
                {
                    $groups->unread = isset($this->_request->unreads[$groups->groupid]) ? $this->_request->unreads[$groups->groupid] : 0;
                    $page['groups'][] = $groups->asArray();
                }
                unset($groups);
                break;
                
            case 'index':
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);

                $page += $this->_getPage('menu', $datas, true);
                $page += $this->_getPage('news', $datas, true);

                $query = '
    SELECT MIN(ttl) AS ttl
        FROM streams s
        JOIN streams_relations sr ON (s.id=sr.rssid)
        WHERE sr.uid='.$this->_user->getUid();
                $ttl = $this->_db->getOne($query);
                $ttl->next();
                $ttl = $ttl->ttl ? $ttl->ttl : $this->_cfg->get('defaultStreamRefreshTime');
                $page += array(
                            'ttl'=>$ttl*60*1000, 
                            'lang'=>$this->_user->getLang(),
                            'surl'=>$this->_cfg->get('surl'),
                            'sort'=>$datas['sort'],
                            'dir'=>$datas['dir'],
                            'uid'=>$this->_user->getUid()
                );
                break;

            case 'opml':
                $page['userlogin'] = $this->_user->getLogin();
                $page['streams'] = array();
                $xml = true;
                $query = '
                    SELECT name, id
                        FROM streams_groups
                        WHERE uid='.$this->_user->getUid().'
                        ORDER BY name';
                
                $groups = $this->_db->getAll($query);
                if(!$groups->count())
                {
                    break;
                }
                
                $query = '
    SELECT DISTINCT(r.id), r.url, rc.contents, r.lastupd, r.ttl, rrn.name
        FROM streams r
        JOIN streams_contents rc ON (r.id=rc.rssid)
        JOIN streams_relations rr ON (rr.rssid=r.id)
        JOIN streams_relations_name rrn ON (rrn.rssid=rr.rssid AND rrn.uid=rr.uid)
        WHERE rr.uid=? AND rr.gid=?
        ORDER BY name';

                while($groups->next())
                {
                    $groups->id = (int)$groups->id;

                    if(!isset($page['groups'][$groups->id]))
                        $page['groups'][$groups->id] = $groups->name;
                    
                    $streams = $this->_db->getAllP($query, new DBRequest(array($this->_user->getUid(), $groups->id)));
                    while($streams->next())
                    {
                        $streams->contents = (array) unserialize($streams->contents);
                        $page['streams'][$groups->id][] = $streams->asArray();
                    }
                }
                unset($streams, $groups);
                break;
                

            case 'upload':
                $page['surl'] = $this->_cfg->get('surl');
                $page['token'] = $this->_user->getToken();
                $page['maxuploadfilesize'] = $this->_cfg->get('maxUploadFileSize');
                $query = "
    SELECT id, name
        FROM streams_groups
        WHERE uid=".$this->_user->getUid()."
        ORDER BY name";
                
                $groups = $this->_db->getAll($query);
                if(!$groups)
                {
                    break;
                }

                while($groups->next())
                {
                    $page['groups'][$groups->id] = $groups->name;
                }
                unset($groups);
            break;
            
            case 'edituser':
                $page['surl'] = $this->_cfg->get('surl');
                $page['token'] = $this->_user->getToken();
                $page['timezones'] = $this->_user->getTimeZones();
                $page['userrights'] = $this->_user->getRights();
                if(!isset($datas['id'])) break;

                $datas['id'] = (int)$datas['id'];
                if(($this->_user->isAdmin() && $datas['id'] > 0) || 
                    ($datas['id'] === $this->_user->getUid()))
                {
                    $query = '
    SELECT login, rights, lang, email, openid, timezone
        FROM users
        WHERE id='.(int)$datas['id'];
                    
                    $user = $this->_db->getRow($query);
                    if(!$user->next()) break; // strange :-D, surely a bug
                    
                    $user->timezone = $this->_user->getTimeZones($user->timezone); // check

                    $page += array_merge($user->asArray(), $datas);
                    unset($user);
                }
            break;
            
            case 'users':
                $page['surl'] = $this->_cfg->get('surl');
                $query = '
    SELECT id, login, email, rights, openid
        FROM users
        ORDER BY rights DESC, login';
                $users = $this->_db->getAll($query);
                $page['users'] = array();
                $page['nbusers'] = $users->count();
                if(!$page['nbusers']) break;
                $page['users'] = $users->getAllNext();
                unset($users);
            break;
            
            case 'rss':
                $xml = true;
                $page['surl'] = $this->_cfg->get('surl');
                $page['userlogin'] = $this->_user->getLogin();
                $query = '
    SELECT DISTINCT(n.id), n.title, n.link, n.pubDate
        FROM news n
        JOIN news_relations nr ON (nr.newsid=n.id)
        WHERE nr.uid='.$this->_user->getUid().' AND nr.status=1';
                    
                if(isset($datas['id']) && 0 !== (int)$datas['id'])
                {
                    $query .= ' AND n.rssid='.(int)$datas['id'];
                } else $datas['id'] = 0;
                
                $page['news'] = $ids = array();
                
                $rows = $this->_db->getAll($query);
                while($rows->next())
                {
                    $ids[] = $rows->id;
                    $query = '
    SELECT contents
        FROM news_contents
        WHERE id='.$rows->id;
                    $contents = $this->_db->cGetOne($query);
                    $rows->contents = (array) ($contents->next() ? unserialize($contents->contents) : '');
                    $rows->pubDate = date(DATE_RSS, $rows->pubDate);
                    $page['news'][] = $rows->asArray();
                }
                unset($contents, $rows);
                
                if($ids)
                {
                    $query = '
    UPDATE news_relations
        SET status=0
        WHERE uid='.$this->_user->getUid().' AND newsid IN ('.join(',', $ids).')';
                    $this->_db->set($query);
                }
            break;
            
            case 'login':
                $page['surl'] = $this->_cfg->get('surl');
                $page['xmlLang'] = $this->_user->getXMLLang();
                $page['token'] = $this->_user->getToken();
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

        if(!empty($request->order))
        {
            $order = $request->order.' '.$request->dir;
        }

        if(!empty($request->offset))
        {
            $offset = ($request->offset * 10).',10';
        }

        $this->_request->page = Logic::getCachedLogic($type)->view($request, array(), $order, '', $offset);

        return $this;
    }
}