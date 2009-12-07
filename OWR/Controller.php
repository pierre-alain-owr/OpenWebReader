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
 */
namespace OWR;
use OWR\DB\Request as DBRequest,
    OWR\Logic\Response as LogicResponse;
if(!defined('INC_CONFIG')) die('Please include config file');
/**
 * This object is the front door of the application
 * @uses DAO deals with database
 * @uses Config the config instance
 * @uses Cron manages cron settings
 * @uses Singleton implements the singleton pattern
 * @uses DB the database link
 * @uses View the page renderer
 * @uses Session session managing
 * @uses Request the request to execute
 * @uses User the current user
 * @uses Exception the exceptions handler
 * @uses Error the errors handler
 * @uses OWR\DB\Request a request sent to database
 * @uses Logs the logs/errors storing object
 * @package OWR
 */
class Controller extends Singleton
{
    /**
     * @var mixed the Config instance
     * @access protected
     */
    protected $_cfg;

    /**
     * @var boolean are we called by the upload frame ?
     * @access protected
     */
    protected $_isFrame = false;

    /**
     * @var mixed the DB instance
     * @access protected
     */
    protected $_db;

    /**
     * @var mixed the View instance
     * @access protected
     */
    protected $_view;

    /**
     * @var mixed the Session instance
     * @access protected
     */
    protected $_sh;

    /**
     * @var array the list of timezones
     * @access protected
     */
    protected $_tz;

    /**
     * @var mixed the current User instance
     * @access protected
     */
    protected $_user;

    /**
     * @var mixed the \IntlDateFormatter instance
     * @access protected
     */
    protected $_dateFormatter;

    /**
     * @var mixed the Cron instance
     * @access protected
     */
    protected $_cron;

    /**
     * @var int the actual minimum ttl
     * @access protected
     */
    protected $_minCronTtl;

    /**
     * @var mixed the current Request instance
     * @access protected
     */
    protected $_request;

    /**
     * Constructor, sets : all needed instances, session handler, errors and exceptions handler,
     * starts the session, and register the user session
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

        set_error_handler(array(__NAMESPACE__.'\Error', 'error_handler')); // errors
        set_exception_handler(array(__NAMESPACE__.'\Exception', 'exception_handler')); // exceptions not catched
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
            $this->_db = DB::iGet(); // init DB connexion
        }
        catch(Exception $e)
        {
            throw new Exception($e->getContent(), Exception::E_OWR_UNAVAILABLE);
        }

        $this->_sh = Session::iGet(); // init session
        
        try
        {
            $this->_sh->init(array('sessionLifeTime' => $this->_cfg->get('sessionLifeTime'),
                'path' => $this->_cfg->get('path'),
                'domain' => $this->_cfg->get('url'),
                'httpsecure' => $this->_cfg->get('httpsecure')));
        }
        catch(Exception $e)
        {
            throw new Exception($e->getContent(), Exception::E_OWR_UNAVAILABLE);
        }

        $this->_user = $this->_sh->get('User');
        if(!isset($this->_user) || !($this->_user instanceof User))
        {
            $this->_user = User::iGet();
            $this->_user->reg(); // populate into the session
        }
    }
    
    /**
     * Executes the given action
     * This method only accepts a Request object
     * It will try to log the user in, and execute the action
     * Throws a fatal Exception if something goes really wrong
     *
     * If you want to execute an action without the controller displays anything
     * set $isInternal to true, and all errors will be logged instead
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed Request the request to execute
     * @access public
     * @return $this
     */
    public function execute(Request $request)
    {
        $this->_request = $request;
        $this->_request->begintime = microtime(true);

        try 
        {
            if(!empty($this->_request->identifier) || 'verifyopenid' === $this->_request->do)
            { // openid, add it to include_path
                ini_set('include_path', HOME_PATH.'libs'.DIRECTORY_SEPARATOR.
                        'openID'.DIRECTORY_SEPARATOR.PATH_SEPARATOR.ini_get('include_path'));
            }

            if(!$this->_user->isLogged())
            {
                if(!empty($this->_request->tlogin) && !empty($this->_request->key))
                {
                    switch($this->_request->do)
                    { // atm only getting stream is allowed, but for the future ..
                        case 'getrss':
                        case 'getopml':
                            break;
                        default: 
                            throw new Exception('Invalid action '.$this->_request->do, Exception::E_OWR_BAD_REQUEST);
                            break;
                    }
                   
                    $this->do_login(true);
                }
                elseif($this->_request->do !== 'edituser' && $this->_request->do !== 'login' && $this->_request->do !== 'verifyopenid')
                {
                    $this->_user->regenerateToken();
                    $this->redirect('login');
                }
            }
            else
            {
                $token = $this->_user->getToken();
                // check HTTP User-Agent and token
                if(($this->_user->getAgent() !== md5($token.$_SERVER['HTTP_USER_AGENT'])) ||
                    empty($this->_request->token) || $this->_request->token !== $token)
                {
                    if($this->_request->do !== 'logout')
                    { // for external action, there's no tokens set
                    // we prompt the user to log-in to confirm he is really who he pretends to be
                        if($this->_request->do === 'opensearch' || $this->_request->do === 'add')
                            $this->_request->back = basename(trim(Filter::iGet()->purify($_SERVER['REQUEST_URI'])));
                        $this->_getPage('login', array('error'=>'You lost your token ! Confirm back your identity'));
                        return $this;
                    }
                }
                unset($token);
            }
    
            $action = 'do_'.$this->_request->do;
    
            if(!method_exists($this, $action)) // surely change this to a __call function to allow plugin
                throw new Exception('Invalid action "'.$this->_request->do.'"', Exception::E_OWR_BAD_REQUEST);
        
            if($this->_user->isAdmin())
            {
                // we redirect if some clear caching is asked
                // to not have '?clear(db|html)cache' in the url
                if(!empty($this->_request->clearcache))
                {
                    Cache::clear();
                    $this->redirect();
                }
                elseif(!empty($this->_request->clearhtmlcache))
                {
                    Cache::clearHTML();
                    $this->redirect();
                }
                elseif(!empty($this->_request->cleardbcache))
                {
                    Cache::clearDB();
                    $this->redirect();
                }
            }

            $this->$action(); // execute the given action
        } 
        catch(Exception $e) 
        {
            $this->_db->rollback();

            throw new Exception($e->getContent(), $e->getCode());
        }

        return $this;
    }

    /**
     * Returns a clone of the current request object
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return mixed clone of the current request
     * @access public
     */
    public function getRequest()
    {
        return clone($this->_request);
    }

    /**
     * Render the page
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param int $statusCode HTTP status code, usefull for errors
     * @return $this
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

        isset($this->_view) || $this->_view = View::iGet();

        if(isset($_SERVER['HTTP_ACCEPT']) && (false !== strpos($_SERVER['HTTP_ACCEPT'], 'application/json')))
        {
            $this->_view->addHeaders(array('Content-Type' => 'application/json; charset=utf-8'));
            $page = array('contents' => '');

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
                    $errors = Logs::iGet()->getLogs();
    
                    foreach($errors as $errcode=>$errmsg)
                    {
                        if(Exception::E_OWR_DIE === $errcode)
                        {
                            $this->_cleanIndent($errmsg);
                            foreach($errmsg as $err)
                                $page['errors'][] = $err;
                        }
                    }

                    if(empty($page['errors'])) $page['errors'][] = 'Non-blocking error(s) occured';
                }
            }
            
            if(empty($page['errors']) && isset($_SERVER['REQUEST_METHOD']) && 'GET' === $_SERVER['REQUEST_METHOD'])
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
            $page['renderingTime'] = round(View::getTime(), 6);
            $page = json_encode($page);
        }
        else
        {
            $this->_view->addHeaders(array('Content-type' => 'text/html; charset=utf-8'));
            if(Logs::iGet()->hasLogs())
            {
                if(DEBUG || $this->_user->isAdmin())
                {
                    $errors = Logs::iGet()->getLogs();
                    $err = array();
                    foreach($errors as $errcode=>$errmsg)
                    {
                        $this->_cleanIndent($errmsg);
                        foreach($errmsg as $msg)
                            $err[] = $msg;
                    }

                    $this->_request->page .= '<script type="text/javascript">';
                    $this->_request->page .= "var e=window.parent||window;if(e.addEvent){ e.addEvent('domready', function(){ if(e.rP) { e.rP.setLogs(e.JSON.decode('".addslashes(json_encode($err))."'),true); }else{ e.document.write('".addslashes(join('<br/>', $err))."'); }});} else {e.document.write('".addslashes(join('<br/>', $err))."');}";
                    $this->_request->page .= '</script>';
                    $this->_request->page .= '<noscript>';
                    $this->_request->page .= join('<br/>', $err);
                    $this->_request->page .= '</noscript>';
                }
                else
                {
                    $errors = Logs::iGet()->getLogs();
                    $err = array();
                    foreach($errors as $errcode=>$errmsg)
                    {
                        if(Exception::E_OWR_DIE === $errcode)
                        {
                            $this->_cleanIndent($errmsg);
                            foreach($errmsg as $msg)
                                $err[] = $msg;
                        }
                    }

                    if(empty($page['errors'])) $page['errors'][] = 'Non-blocking error(s) occured';

                    Logs::iGet()->writeLogs();

                    if(!empty($err))
                    {
                        $this->_request->page .= '<script type="text/javascript">';
                        $this->_request->page .= "var e=window.parent||window;if(e.addEvent){ e.addEvent('domready', function(){ if(e.rP) { e.rP.setLogs(e.JSON.decode('".addslashes(json_encode($err))."'),true); }else{ e.document.write('".addslashes(join('<br/>', $err))."'); }});} else {e.document.write('".addslashes(join('<br/>', $err))."');}";
                        $this->_request->page .= '</script>';
                        $this->_request->page .= '<noscript>';
                        $this->_request->page .= join('<br/>', $err);
                        $this->_request->page .= '</noscript>';
                    }
                    else
                    {
                        if(isset($_SERVER['REQUEST_METHOD']) && 'GET' === $_SERVER['REQUEST_METHOD'])
                        {
                            $etag = '"owr-'.md5($this->_request->page).'"';
                            $this->_view->addHeaders(array(
                                'Cache-Control' => 'Public, must-revalidate',
                                "Expires" => gmdate("D, d M Y H:i:s", $this->_request->begintime + $this->_cfg->get('cacheTime'))." GMT",
                                'Etag' => $etag
                            ), true);
                            if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
                            {
                                $this->_view->setStatusCode(304, true);
                                flush();
                                return $this;
                            }
                        }
                        $now = microtime(true);
                        $this->_request->page .= '<!-- Execution time: '.round($now - $this->_cfg->get('begintime'), 6).'s (Request time: '. round($now - $this->_request->begintime, 6).'s => '.round(DB::getTime(), 6).'s of SQL) -->';
                    }
                }

                unset($errors, $err);
            }
            else
            {
                if(isset($_SERVER['REQUEST_METHOD']) && 'GET' === $_SERVER['REQUEST_METHOD'])
                {
                    $etag = '"owr-'.md5($this->_request->page).'"';
                    $this->_view->addHeaders(array(
                        'Cache-Control' => 'Public, must-revalidate',
                        "Expires" => gmdate("D, d M Y H:i:s", $this->_request->begintime + $this->_cfg->get('cacheTime'))." GMT",
                        'Etag' => $etag
                    ), true);
                    if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
                    {
                        $this->_view->setStatusCode(304, true);
                        flush();
                        return $this;
                    }
                }

                $now = microtime(true);
                $this->_request->page .= '<!-- Execution time: '.round($now - $this->_cfg->get('begintime'), 6).'s (Request time: '. round($now - $this->_request->begintime, 6).'s => '.round(DB::getTime(), 6).'s of SQL, '.round(View::getTime(), 6).'s of page rendering) -->';
            }
            
            $page =& $this->_request->page;
        }

        $this->_view->setStatusCode($statusCode, true);

        $this->_view->render($page);

        $this->_request->page = null;

        return $this;
    }

    /**
     * Adds a string to $this->_request->page
     *
     * @access public
     * @param mixed $content the content to add (string if page=string, associative array if page=array)
     */
    public function addToPage($content)
    {
        if(is_array($this->_request->page))
        {
            $content = (array) $content;
            foreach($content as $k=>$v)
                $this->_request->page[$k] = (string) $v;
        }
        else
        {
            $this->_request->page .= (string) $content;
        }
    }

    /**
     * Redirects the user to a specific page
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $url the url to redirect
     * @access public
     */
    public function redirect($url = null)
    {
        $url = (string) $url;

        if('login' === $url)
        {
            $params = 'timeout=1';
            if(isset($_SERVER['REQUEST_URI']) && (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest'))
            {
                $request = trim(Filter::iGet()->purify($_SERVER['REQUEST_URI']));
                $current = basename($request);
                if(false === strpos($current, 'logout') && false === strpos($current, 'login') && $this->_cfg->get('path') !== $request)
                    $params .= '&back='.urlencode($current);
            }
        }
        else
        {
            $params = 'token='.$this->_user->getToken();
        }

        $surl = $this->_cfg->makeURI($url, $params, false);

        if(isset($_SERVER['HTTP_ACCEPT']) && (false !== strpos($_SERVER['HTTP_ACCEPT'], 'application/json')))
        {
            $page = json_encode(array('location' => $surl));
            isset($this->_view) || $this->_view = View::iGet();
            $this->_view->render($page);
        }
        elseif(!$this->_isFrame && !headers_sent())
        {
            header('Location: '.$surl);
        } 
        else
        {
            $page = '<a href="'.$surl.'">Redirection</a>';
            $page .= '<script type="text/javascript">';
            $page .= $this->_isFrame ? 'window.parent.location.href="'.$surl.'";' : 'window.location.href="'.$surl.'";';
            $page .= '</script>';
            $page .= '<noscript>';
            $page .= '<meta http-equiv="refresh" content="0;url='.$surl.'" />';
            $page .= '</noscript>';
            isset($this->_view) || $this->_view = View::iGet();
            $this->_view->render($page);
        }
        exit;
    }

    /**
     * Process the response of a Logic call
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param mixed LogicResponse the response of the 
     */
    public function processResponse(LogicResponse $response)
    {
        $status = $response->getStatus();
        if($status)
            View::iGet()->setStatusCode($status);

        switch($response->getNext())
        {
            case 'redirect': // redirection
                $this->redirect($response->getLocation()); // implicit exit
                break;

            case 'error': // error
                $this->_request->errors = $response->getErrors();
                $tpl = $response->getTpl();
                if($tpl)
                {
                    $this->_getPage($response->getTpl(), $response->getDatas());
                }
                else Logs::iGet()->log($response->getError(), $response->getStatus());
                $ret = false;
                break;

            case 'ok': // ok !
                $ret = true;
                $tpl = $response->getTpl();
                if($tpl)
                {
                    $this->_getPage($tpl, $response->getDatas());
                }
                break;

            default:
                throw new Exception('Invalid return from Logic', Exception::E_OWR_DIE);
                break;
        }

        return $ret;
    }

    /**
     * Returns a date in user lang from a timestamp
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param int $timestamp the timestamp to convert
     * @return string the date
     * @access protected
     */
    protected function _getDate($timestamp)
    {
        isset($this->_dateFormatter) ||
        $this->_dateFormatter = new \IntlDateFormatter(
            $this->_user->getLang(), 
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::MEDIUM
        );
        return $this->_dateFormatter->format((int)$timestamp);
    }

    /**
     * Removes whitespaces characters
     * Used for javascript response that can not handle them
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed &$contents the contents to clean, array or string
     * @access protected
     */
    protected function _cleanIndent(&$contents)
    {
        if(is_array($contents))
        {
            array_walk_recursive($contents, array($this, '_cleanIndent'));
        }
        else
        {
            $contents = preg_replace('/(\s)\s+/s', "\\1", (string) $contents);
            $contents = str_replace("\n\n", ' ', $contents);
            $contents = str_replace("\n", ' ', $contents);
            $contents = str_replace("\r\r", '', $contents);
            $contents = str_replace("\r", '', $contents);
            $contents = str_replace("\t\t", ' ', $contents);
            $contents = str_replace("\t", ' ', $contents);
            $contents = str_replace('  ', ' ', $contents);
        }
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
        isset($this->_view) || $this->_view = View::iGet();

        $cacheTime = $this->_cfg->get('cacheTime');
        $noCacheDatas = array();
        $empty = false;
        $page = '';

        switch($tpl)
        {
            case 'new_contents':
                $request = new Request(array('id' => $datas['id']));
                Logic::getCachedLogic('news')->view($request);
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $page .= $this->_view->get('new_contents', $response->getDatas(), $cacheTime);
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                }
                unset($response, $request);
            break;

            case 'new_details':
                $datas['details'] = array();
                $request = new Request(array('id' => $datas['id']));
                Logic::getCachedLogic('news')->view($request);
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $data = $response->getDatas();
                    $datas['url'] = htmlspecialchars($data['link'], ENT_COMPAT, 'UTF-8');
                    $datas['title'] = htmlspecialchars($data['title'], ENT_COMPAT, 'UTF-8');
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
                                $datas['details'][$k] = $content;
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
                $datas['gname'] = $datas['name'];
                $datas['groupid'] = $datas['gid'];
                
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);

                $noCacheDatas['unread'] = isset($this->_request->unreads[$datas['gid']]) ? $this->_request->unreads[$datas['gid']] : 0;
                $noCacheDatas['bold'] = $noCacheDatas['unread'] > 0 ? 'bold ' : '';
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
                Logic::getCachedLogic('streams_groups')->view($request, array(), 'name');
                $response = $request->getResponse();
                $groups = array();
                if('error' !== $response->getNext())
                {
                    $groups = $response->getDatas();
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                    break;
                }
                unset($response, $g);
                $request->getContents = false;
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

                        if(!isset($groups_select[$stream['gid']]))
                            $groups_select[$stream['gid']] = $this->_view->get('menu_selects', array(
                                                                        'gid' => $stream['gid'],
                                                                        'groups' => $groups),
                                                                        $cacheTime
                                                        );
                        $unread = isset($this->_request->unreads[$stream['id']]) ? $this->_request->unreads[$stream['id']] : 0;
                        $page .= $this->_view->get('menu_streams', $stream, $cacheTime, array(
                                    'unread'    => $unread,
                                    'bold'      => $unread > 0 ? 'bold ' : '',
                                    'groups_select'     => $groups_select[$stream['gid']]
                                    ));
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
                $request = new Request(array('id' => $datas['id']));
                Logic::getCachedLogic('streams')->view($request);
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $datas['stream'] = $response->getDatas();
                    unset($datas['stream']['title']);
                    $datas['stream']['contents']['nextRefresh'] = $this->_getDate($datas['stream']['lastupd'] + $datas['stream']['ttl']);
                    $datas['stream']['contents']['id'] = $datas['stream']['id'];
                    $datas['stream']['contents']['url'] = $datas['stream']['url'];
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
                    $datas['offset'] = (int)$datas['offset'];
                    
                    if($datas['offset'] > 0)
                    {
                        $offset = (int)($datas['offset']*10);
                    }
                }
                else
                {
                    $datas['offset'] = 0;
                }

                $offset = $offset.',10'; // TODO : change this limit by the user defined one

                if(isset($datas['id']) && is_array($datas['id']))
                {
                    if(empty($datas['id']))
                    {
                        $empty = true;
                        break;
                    }
                    $request = new Request(array('ids' => $datas['id'], 'getContents' => false));
                    Logic::getCachedLogic('news')->view($request, array(), $order, 'news.id', $offset);
                    $datas['nbNews'] = count($datas['id']);
                }
                elseif(empty($datas['id']))
                {
                    $request = new Request(array('id' => null, 'getContents' => false));
                    Logic::getCachedLogic('news')->view($request, array('status' => 1), $order, 'news.id', $offset);
                    $datas['nbNews'] = $this->_request->unreads[0];
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

                    $request = new Request(array('id' => null, 'getContents' => false));
                    if('streams' === $table)
                    {
                        Logic::getCachedLogic('news')->view($request, array('rssid' => $datas['id']), $order, 'news.id', $offset);
                        $nb = DAO::getCachedDAO('news_relations')->count(array('rssid' => $datas['id']), 'newsid');
                        $datas['nbNews'] = $nb ? $nb->nb : 0;
                    }
                    elseif('streams_groups' === $table)
                    {
                        Logic::getCachedLogic('news')->view($request, array('gid' => $datas['id']), $order, 'news.id', $offset);
                        $nb = DAO::getCachedDAO('news_relations')->count(array('gid' => $datas['id']), 'newsid');
                        $datas['nbNews'] = $nb ? $nb->nb : 0;
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

                $pager = array('nbNews'     => (int) $datas['nbNews'],
                                'offset'    => $datas['offset'],
                                'sort'      => !empty($datas['sort']) ? $datas['sort'] : null,
                                'dir'       => !empty($datas['dir']) ? $datas['dir'] : null);

                if(empty($datas['search']))
                {
                    $pager = $this->_view->get('news_tools', $pager, $cacheTime);
                    $page .= $pager;
                }

                foreach($news as $new)
                {
                    if(isset($datas['searchResults'][$new['id']]))
                        $new['search_result'] = (float) $datas['searchResults'][$new['id']];
                    $new['pubDate'] = $this->_getDate($new['pubDate']);
                    $page .= $this->_view->get('new_title', $new, $cacheTime);
                }
                unset($news);

                if(empty($datas['search']))
                {
                    $page .= $pager;
                    unset($pager);
                }
            break;

            case 'index':
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);
                $token = $this->_user->getToken();
                $surl = $this->_cfg->get('surl');
                $ulang = $this->_user->getLang();
                $uid = $this->_user->getUid();
                $page .= $this->_view->get('header', array(
                                                        'surl'      =>$surl, 
                                                        'xmlLang'   =>$this->_user->getXMLLang(),
                                                    ), 
                                                    $cacheTime,
                                                    array(
                                                        'token' => $token
                                                    ));

                $page .= $this->_view->get('board', array(
                                                        'lang' => $ulang,
                                                        'surl' => $surl,
                                                    ), 
                                                    $cacheTime,
                                                    array(
                                                        'userlogin' => htmlentities($this->_user->getLogin(), ENT_COMPAT, 'UTF-8'),
                                                        'token'     => $token
                                                    ));

                $request = new Request(array('id'=>null));
                Logic::getCachedLogic('streams_groups')->view($request);
                $response = $request->getResponse();
                $groups = array();
                $tmpPage = '';
                if('error' !== $response->getNext())
                {
                    $groups = $response->getDatas();
                    if(!empty($groups))
                    {
                        if($response->isMultiple())
                        {
                            foreach($groups as $group)
                            {
                                $group['groupid'] = $group['id'];
                                $group['gname'] = $group['name'];
                                $noCacheDatas['unread'] = isset($this->_request->unreads[$group['id']]) ? $this->_request->unreads[$group['id']] : 0;
                                $noCacheDatas['bold'] = $noCacheDatas['unread'] > 0 ? 'bold ' : '';
                                $tmpPage .= $this->_view->get('menu_contents', $group, $cacheTime, $noCacheDatas);
                            }
                        }
                        else
                        {
                            $groups['groupid'] = $groups['id'];
                            $groups['gname'] = $groups['name'];
                            $noCacheDatas['unread'] = isset($this->_request->unreads[$groups['id']]) ? $this->_request->unreads[$groups['id']] : 0;
                            $noCacheDatas['bold'] = $noCacheDatas['unread'] > 0 ? 'bold ' : '';
                            $tmpPage .= $this->_view->get('menu_contents', $groups, $cacheTime, $noCacheDatas);
                            $groups = array($groups);
                        }
                    }
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                }
                unset($response, $request);
                $page .= $this->_view->get('contents_header', array(), $cacheTime);
                $page .= $this->_getPage('news', $datas, true);
                $page .= $this->_view->get('contents_footer', array(), $cacheTime);
                $page .= $this->_view->get('menu_header', array(), $cacheTime, 
                                            array(
                                                'unread' => $this->_request->unreads[0], 
                                                'bold' => $this->_request->unreads[0] > 0 ? ' class="bold"' : ''
                                            ));
                $page .= $tmpPage;
                unset($tmpPage);
                $page .= $this->_view->get('menu_footer', array(
                                                            'groups'            => $groups, 
                                                            'userrights'        => $this->_user->getRights(),
                                                            'surl'              => $surl,
                                                            'maxuploadfilesize' => $this->_cfg->get('maxUploadFileSize')
                                                        ), 
                                                        $cacheTime,
                                                        array(
                                                            'token'             => $token,
                                                            'uid'               => $uid,
                                                            'groups_select'     => $this->_view->get('menu_selects', array(
                                                                        'gid' => 0,
                                                                        'groups' => $groups),
                                                                        $cacheTime
                                                        )));
                unset($groups);
                $page .= $this->_view->get('footer', array(
                                                        'lang'=>$ulang,
                                                        'surl'=>$surl
                                                        ), 
                                                        $cacheTime,
                                                    array(
                                                        'token'=>$token,
                                                        'ttl'=>$this->_cfg->get('defaultMinStreamRefreshTime')*60*1000,
                                                        'opensearch'=>(isset($datas['opensearch']) ? $datas['opensearch'] : 0)
                                                    ));
                break;

            case 'getopensearch':
                $datas['surl'] = $this->_cfg->get('surl');
                break;

            case 'opml':
                $datas['userlogin'] = $this->_user->getLogin();
                $noCacheDatas['dateCreated'] = $datas['dateCreated'];
                unset($datas['dateCreated']);
                $datas['streams'] = array();
                $request = new Request(array('id'=>null));
                Logic::getCachedLogic('streams')->view($request);
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $streams = $response->getDatas();
                    if(empty($streams)) break;

                    if($response->isMultiple())
                    {
                        foreach($streams as $stream)
                        {
                            if(!isset($datas['groups'][$stream['gid']]))
                                $datas['groups'][$stream['gid']] = $stream['gname'];
                            $datas['streams'][$stream['gid']][] = $stream;
                        }
                    }
                    else
                    {
                        $datas['groups'][$streams['gid']] = $streams['gname'];
                        $datas['streams'][$streams['gid']][] = $streams;
                    }
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                }
                unset($response, $request, $streams);
                break;

            case 'edituser':
                $noCacheDatas['token'] = $this->_user->getToken();
                if(empty($datas['id']))
                { // surely editing a new user
                    $datas['surl'] = $this->_cfg->get('surl');
                    $datas['timezones'] = $this->_user->getTimeZones();
                    $datas['userrights'] = $this->_user->getRights();
                    break;
                }

                $request = new Request(array('id' => $datas['id']));
                $datas['surl'] = $this->_cfg->get('surl');
                $datas['timezones'] = $this->_user->getTimeZones();
                $datas['userrights'] = $this->_user->getRights();
                Logic::getCachedLogic('users')->view($request);
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $datas += $response->getDatas();
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
                $datas['surl'] = $this->_cfg->get('surl');
                $noCacheDatas['token'] = $this->_user->getToken();
                $datas['users'] = array();
                $datas['nbusers'] = 0;
                Logic::getCachedLogic('users')->view($request, array(), 'login');
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    if(!$response->isMultiple())
                    {
                        $datas['users'][] = $response->getDatas();
                        $datas['nbusers'] = 1;
                    }
                    else
                    {
                        $datas['users'] = $response->getDatas();
                        $datas['nbusers'] = count($datas['users']);
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
                $request = new Request(array('id'=>null));
                $datas['surl'] = $this->_cfg->get('surl');
                $datas['userlogin'] = $this->_user->getLogin();
                $args = array('status' => 1);
                if(!empty($datas['id']))
                    $args['rssid'] = $datas['id'];
                $datas['news'] = $ids = array();
                Logic::getCachedLogic('news')->view($request, $args);
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $data = $response->getDatas();
                    if(empty($data)) break;

                    if($response->isMultiple())
                    {
                        $datas['news'] = $data;
                        unset($data);
                        foreach($datas['news'] as $k=>$new)
                        {
                            $ids[] = $new['id'];
                            $datas['news'][$k]['pubDate'] = date(DATE_RSS, $new['pubDate']);
                        }
                    }
                    else
                    {
                        $ids[] = $data['id'];
                        $data['pubDate'] = date(DATE_RSS, $data['pubDate']);
                        $datas['news'][] = $data;
                    }

                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    $empty = true;
                }
                unset($response, $request);
                
                if(!empty($ids))
                {
                    $query = '
    UPDATE news_relations
        SET status=0
        WHERE uid='.$this->_user->getUid().' AND newsid IN ('.join(',', $ids).')';
                    $this->_db->set($query);
                }
                break;
            
            case 'login':
                $datas['surl'] = $this->_cfg->get('surl');
                $datas['xmlLang'] = $this->_user->getXMLLang();
                $noCacheDatas['token'] = $this->_user->getToken();
                $noCacheDatas['back'] = $this->_request->back;
                break;

            default: 
                break;
        }
        
        if(!empty($page))
        {
            if($return)
                return $page;
            else
                $this->_request->page .= $page;
        }
        elseif(!$empty)
        {
            if($return) 
                return $this->_view->get($tpl, $datas, $cacheTime, $noCacheDatas);
            else 
                $this->_request->page .= $this->_view->get($tpl, $datas, $cacheTime, $noCacheDatas);
        }
    }

    /**
     * Methods bellow are actions to be executed by $this->execute()
     * They all are prefixed with do_*
     * @access protected
     * @return $this
     */

    /**
     * Now functions that do not require a logic call
     */

    /**
     * Redirects to an external operator
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_redirectOperator()
    {
        if(!$this->_request->id)
            throw new Exception('Missing id', Exception::E_OWR_BAD_REQUEST);

        if(!$this->_request->operator)
            throw new Exception('Missing operator', Exception::E_OWR_BAD_REQUEST);

        if('news' !== DAO::getType($this->_request->id))
            throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);

        $operator = new Operator($this->_request->operator);
        $operator->redirect(DAO::getCachedDAO('news')->get($this->_request->id, 'title, link'));

        return $this;
    }

    /**
     * Renders the stream template related to the specified id
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getRSS()
    {
        isset($this->_view) || $this->_view = View::iGet();
        $this->_view->addHeaders(array('Content-Type' => 'text/xml; charset=utf-8'));
        $this->_getPage('rss', array('id'=>$this->_request->id));
        return $this;
    }

    /**
     * Renders the unreads news count
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getLastNews()
    {
        return $this->do_getunread(true);
    }

    /**
     * Renders the details of a new for a specific id
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getNewDetails()
    {
        $this->_getPage('new_details', array('id' => $this->_request->id));
        return $this;
    }

    /**
     * Renders the category template for a specific id
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getMenuPartGroup()
    {
        $this->_getPage('menu_part_group', array('id'=>$this->_request->id));
        return $this;
    }

    /**
     * Renders the stream template for a specific id
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     */
    protected function do_getMenuPartStream()
    {
        $this->_getPage('menu_part_stream', array('id'=>$this->_request->id));
        return $this;
    }
    
    /**
     * Renders news template from a specific stream with a specific offset
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getStream()
    {
        if(0 < $this->_request->id)
        {
            $type = DAO::getType($this->_request->id);
            
            if('streams' !== $type && 'streams_groups' !== $type)
                throw new Exception('Invalid Id', Exception::E_OWR_BAD_REQUEST);
        }

        $this->_getPage('news', array( 
                            'id'        => $this->_request->id, 
                            'offset'    => $this->_request->offset,
                            'sort'      => $this->_request->sort,
                            'dir'       => $this->_request->dir
        ));
        return $this;
    }

    /**
     * Renders the count of unreads news for a specific id
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getLiveNews()
    {
        if(!$this->_request->id)
        {
            $nb = DAO::getCachedDAO('news_relations')->count(array('status' => 1));
        }
        else
        {
            $type = DAO::getType($this->_request->id);
            
            if('streams' === $type)
            {
                $nb = DAO::getCachedDAO('news_relations')->count(array('status' => 1, 'rssid' => $this->_request->id));
            }
            elseif('streams_groups' === $type)
            {
                $nb = DAO::getCachedDAO('news_relations')->count(array('status' => 1, 'gid' => $this->_request->id));
            }
            else throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
        }

        $this->_request->page = $nb ? $nb->nb : 0;
        return $this;
    }
    
    /**
     * Renders or sets the unreads news count
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $return $return sets instead of rendering
     * @access protected
     * @return $this
     */
    protected function do_getUnread($return=false)
    {
        $unreads = array();
        $unreads[0] = 0;

        $this->_request->unreads = array();
        $nb = DAO::getCachedDAO('news_relations')->count(array('status' => 1, 'FETCH_TYPE' => 'array'), 'newsid', 'rssid', 'rssid,gid');
        if($nb)
        {
            if(is_array($nb[0]))
            {
                foreach($nb as $count)
                {
                    $unreads[0] += $count[0];
                    $unreads[$count[1]] = $count[0];
                    isset($unreads[$count[2]]) || ($unreads[$count[2]] = 0);
                    $unreads[$count[2]] += $count[0];
                }
            }
            else
            {
                $unreads[0] += $nb[0];
                $unreads[$nb[1]] = $nb[0];
                $unreads[$nb[2]] = $nb[0];
            }
        }

        if(!$return)
            $this->_request->page = $unreads;
        else
            $this->_request->unreads = $unreads;

        return $this;
    }

    /**
     * Renders the index page
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_index()
    {
        $this->_getPage('index');
        return $this;
    }
    
    /**
     * Renders the list of the users
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected.
     * @return $this
     */
    protected function do_getUsers()
    {
        if(!$this->_user->isAdmin())
            throw new Exception('You don\'t have the rights to do that.', Exception::E_OWR_UNAUTHORIZED);
        
        $this->_getPage('users');
        return $this;
    }

    /**
     * Renders the open search XML declaration
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getOpenSearch()
    {
        isset($this->_view) || $this->_view = View::iGet();
        $this->_view->addHeaders(array('Content-Type' => 'text/xml; charset=utf-8'));
        $this->_getPage('getopensearch');
        return $this;
    }


    /**
     * Renders the contents of the news relative to the specified id
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getNewContents()
    {
        if(!$this->_request->id)
            throw new Exception('An id is required', Exception::E_OWR_BAD_REQUEST);
        
        $type = DAO::getType($this->_request->id);
        if('news' !== $type) throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
        
        if($this->_request->live)
        {
            try
            {
                $this->do_upnew(false, 'news');
            }
            catch(Exception $e)
            {
                switch($e->getCode())
                {
                    case Exception::E_OWR_NOTICE:
                    case Exception::E_OWR_WARNING:
                        Logs::iGet()->log($e->getContent(), $e->getCode());
                        break;
                    default:
                        throw new Exception($e->getContent(), $e->getCode());
                        break;
                }
            }
        }
        
        $this->_getPage('new_contents', array('id'=>$this->_request->id, 'offset'=>$this->_request->offset));
        return $this;
    }
    
    /**
     * Exports the feeds in OPML format
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getOPML()
    {
        isset($this->_view) || $this->_view = View::iGet();
        if(!empty($this->_request->dl))
        {
            $opml = $this->_getPage('opml', array('dateCreated'=>date("D, d M Y H:i:s T")), true);
            $this->_view->addHeaders(array(
                "Pragma" => "public",
                "Expires" => "0",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Content-Type" => "text/x-opml; charset=UTF-8",
                "Content-Transfer-Encoding" => "binary",
                "Content-Length" => mb_strlen($opml, 'UTF-8'),
                "Content-Disposition" => "attachment; Filename=\"OpenWebReader_Feedlist.opml\""
            ));
            $this->addToPage($opml);
        }
        else
        {
            $this->_view->addHeaders(array('Content-Type' => 'text/xml; charset=UTF-8'));
            $this->_getPage('opml', array('dateCreated'=>date("D, d M Y H:i:s T")));
        }
        return $this;
    }

    /**
     * Do some database cleaning/maintenance
     * Must be an administrator
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_maintenance()
    {
        if($this->_user->getRights() < User::LEVEL_ADMIN)
            throw new Exception('You don\'t have the rights to do this. Please ask for your administrator.', Exception::E_OWR_UNAUTHORIZED);

        // remove unused streams
        $query = '
    DELETE FROM objects
        WHERE id IN (
            SELECT id FROM streams
                WHERE id NOT IN (
                    SELECT rssid
                        FROM streams_relations
                        GROUP BY rssid
                )
        )';
        $this->_db->set($query);

        $query = '
    DELETE FROM objects
        WHERE id NOT IN (
            SELECT id FROM streams)
        AND id NOT IN (
            SELECT id FROM streams_groups)
        AND id NOT IN (
            SELECT id FROM users)
        AND id NOT IN (
            SELECT id FROM news)';
        $this->_db->set($query);
        
        $query = '
    DELETE FROM objects
        WHERE id IN (
            SELECT id FROM streams
                WHERE id NOT IN (
                    SELECT rssid
                        FROM streams_relations
                        GROUP BY rssid))';
        
        $this->_db->set($query);

        $query = '
    DELETE FROM news_contents
        WHERE id NOT IN (
            SELECT id
                FROM news)';
        
        $this->_db->set($query);

        $query = '
    OPTIMIZE TABLES 
        news, 
        news_relations, 
        objects, 
        streams, 
        streams_groups, 
        streams_relations, 
        streams_relations_name, 
        sessions, 
        news_contents, 
        users, 
        users_tokens';
        // PDO bug : need to set method to 'query' else it trows exception
        // http://bugs.php.net/bug.php?id=34499
        $this->_db->set($query, null, 'query');

        // we check we have at least one stream
        // if there are none, we remove the crontab if not already empty
        $nb = DAO::getCachedDAO('streams')->count(array(), 'id');
        if((int) $nb->nb === 0)
        {
            isset($this->_cron) || $this->_cron = Cron::iGet();
            $this->_cron->manage('refreshstream');
            $this->_cron->manage('managefavicons');
            $this->_cron->manage('checkstreamsavailability');
        }

        Cache::clear('', true);

        return $this;
    }

    /**
     * Tries to auth user against OpenID
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_verifyOpenID()
    {// openid login
        class_exists('Auth_OpenID_FileStore', false) || include HOME_PATH.'libs/openID/Auth/OpenID/SReg.php';
        $store = new \Auth_OpenID_FileStore($this->_cfg->get('defaultTmpDir'));
        $consumer = new \Auth_OpenID_Consumer($store);

        $result = $consumer->complete($this->_cfg->get('openIDReturn'));
        if($result->status != Auth_OpenID_SUCCESS)
        {
            unset($result);
            $this->redirect('logout');
        }

        $this->do_login(false, $result->getDisplayIdentifier());
        unset($result);

        return $this;
    }

    /**
     * Renders results from a search coming from the search toolbar of your favorite browser
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_openSearch()
    {
        if(empty($this->_request->oskeywords))
        {
            Logs::iGet()->log('Empty search, please enter at least a keyword !', Exception::E_OWR_BAD_REQUEST);
            $datas = array();
            $datas['sort'] = $this->_request->sort ?: '';
            $datas['dir'] = $this->_request->dir ?: '';
            $this->_getPage('index');
            return $this;
        }
        
        $query = '
    SELECT id, MATCH(contents) AGAINST(?) AS result
        FROM news_contents
        WHERE id IN 
            (';

        if(empty($this->_request->id))
            $query .= 'SELECT newsid
                FROM news_relations
                WHERE uid='.$this->_user->getUid();
        else
        {
            $query .= 'SELECT newsid
                FROM news_relations nr
                ';
            $type = DAO::getType($this->_request->id);
            switch($type)
            {
                case 'streams':
                    $query .= '
                WHERE nr.uid='.$this->_user->getUid().' AND rssid='.$this->_request->id;
                    break;

                case 'streams_groups':
                    $query .= '

                JOIN streams_relations sr ON (nr.rssid=sr.rssid)
                WHERE nr.uid='.$this->_user->getUid().' AND sr.uid='.$this->_user->getUid().' AND gid='.$this->_request->id;
                    break;

                default:
                    throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
                    break;
            }
        }

        $query .= ') 
            AND MATCH(contents) AGAINST(? IN BOOLEAN MODE)
        ORDER BY result DESC
        LIMIT 50'; // limit here, 50 is just enough.

        $results = $this->_db->getAllP($query, 
                    new DBRequest(array($this->_request->oskeywords, $this->_request->oskeywords)));
        $datas = array('id' => array(), 'opensearch' => htmlspecialchars($this->_request->oskeywords, ENT_COMPAT, 'UTF-8', false));
        if($results->count())
        {
            while($results->next())
            {
                $results->id = (int)$results->id;
                $datas['id'][] = $results->id;
                $datas['searchResults'][$results->id] = $results->result;
            }
            unset($results);
            $datas['sort'] = $this->_request->sort ?: '';
            $datas['dir'] = $this->_request->dir ?: '';
            $this->_getPage('index', $datas);
        }
        else
        {
            Logs::iGet()->log('No results found. Try again by simplifying the request.', 204);
            $datas['sort'] = $this->_request->sort ?: '';
            $datas['dir'] = $this->_request->dir ?: '';
            $this->_getPage('index', $datas);
        }
        return $this;
    }

    /**
     * Renders results from a search
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_search()
    {
        if(empty($this->_request->keywords))
        {
            throw new Exception('Empty search, please enter at least a keyword !', Exception::E_OWR_BAD_REQUEST);
            return $this;
        }
        
        $query = '
    SELECT id, MATCH(contents) AGAINST(?) AS result
        FROM news_contents
        WHERE id IN 
            (';

        if(empty($this->_request->id))
            $query .= 'SELECT newsid
                FROM news_relations
                WHERE uid='.$this->_user->getUid();
        else
        {
            $query .= 'SELECT newsid
                FROM news_relations nr
                ';
            $type = DAO::getType($this->_request->id);
            switch($type)
            {
                case 'streams':
                    $query .= '
                WHERE nr.uid='.$this->_user->getUid().' AND rssid='.$this->_request->id;
                    break;

                case 'streams_groups':
                    $query .= '

                JOIN streams_relations sr ON (nr.rssid=sr.rssid)
                WHERE nr.uid='.$this->_user->getUid().' AND sr.uid='.$this->_user->getUid().' AND gid='.$this->_request->id;
                    break;

                default:
                    throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
                    break;
            }
        }

        $query .= ') 
            AND MATCH(contents) AGAINST(? IN BOOLEAN MODE)
        ORDER BY result DESC
        LIMIT 50'; // limit here, 50 is just enough.

        $results = $this->_db->getAllP($query, 
                    new DBRequest(array($this->_request->keywords, $this->_request->keywords)));

        if($results->count())
        {
            $datas = array('search' => true);
            while($results->next())
            {
                $results->id = (int)$results->id;
                $datas['id'][] = $results->id;
                $datas['searchResults'][$results->id] = $results->result;
            }
            unset($results);
            $datas['offset'] = $this->_request->offset;
            $datas['sort'] = $this->_request->sort ?: '';
            $datas['dir'] = $this->_request->dir ?: '';
            $this->_getPage('news', $datas);
        }
        else
        {
            Logs::iGet()->log('No results found. Try again by simplifying the request.', 204);
        }
        return $this;
    }

    /**
     * Requires a call to a logic from here
     */

    /**
     * Changes the user interface language
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_changeLang()
    {
        Logic::getCachedLogic('users')->changeLang($this->_request);
        $this->processResponse($this->_request->getResponse());
        return $this;
    }

    /**
     * Tries to auth user
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $auto automatic auth (gateway mode)
     * @param string $openid OpenID authentication, optionnal
     * @access protected
     * @return $this
     */
    protected function do_login($auto=false, $openid=null)
    {
        $exists = DAO::getCachedDAO('users')->get(null, 'id', null, null, 1);
        
        if(!$exists)
        {
            $this->_user->reset();
            $this->_getPage('edituser', array('id'=>0));
            return $this;
        }
        unset($exists);
        
        if(!$auto && empty($_POST) && !isset($openid) && empty($this->_request->identifier))
        {
            $datas = array();
            if(isset($this->_request->timeout)) $datas['error'] = 'Session timeout';
            if(isset($this->_request->back)) $datas['back'] = $this->_request->back;
            $this->_user->reset();
            $this->_getPage('login', $datas);
            return $this;
        }
        
        $uid = 0;
        
        if($auto)
        {
            if(!$this->_user->checkToken(true, $this->_request->uid, $this->_request->tlogin, $this->_request->key, $this->_request->do))
            {
                $this->_user->reset();
                $this->_getPage('login', array('error'=>'Invalid token'));
                return $this;
            }
        }
        elseif($openid)
        {
            $token = $this->_user->getToken();
            // check HTTP User-Agent and token
            if(($this->_user->getAgent() !== md5($token.
            (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'X'))) ||
            $this->_request->token !== $token)
            {
                $this->_user->reset();
                $this->_getPage('login', array('error'=>'Invalid token'));
                return $this;
            }
            $this->_user->openIdAuth($openid);
        }
        elseif(!empty($this->_request->identifier))
        {
            $this->_user->checkToken();
            $login = $this->_request->identifier;
            if(0 !== mb_strpos($login, 'http://', 0, 'UTF-8'))
                $login = 'http://'.$login;
            if('/' !== mb_substr($login, -1, 1, 'UTF-8'))
                    $login .= '/'; 

            class_exists('Auth_OpenID_FileStore', false) || include HOME_PATH.'libs/openID/Auth/OpenID/SReg.php';
            $store = new \Auth_OpenID_FileStore($this->_cfg->get('defaultTmpDir'));
            $consumer = new \Auth_OpenID_Consumer($store);
            $authRequest = $consumer->begin($login);
            $sreg = \Auth_OpenID_SRegRequest::build(array('nickname'), array('fullname', 'email'));
            $authRequest->addExtension($sreg);
            $redirectURL = $authRequest->redirectURL($this->_cfg->get('openIDUrl'), $this->_cfg->get('openIDReturn').'&token='.$this->_user->getToken().
                (!empty($this->_request->back) ? '&back='.urlencode($this->_request->back) : ''));
            if($redirectURL != null)
            {
                header('Location: '.$redirectURL); // Redirection vers l'OP
                exit;
            }

            throw new Exception('Internal error while redirecting to your OP');
        }
        else
        {
            $isLogged = $this->_user->isLogged();
            if(!$this->_user->checkToken())
            {
                $this->_user->reset();
                $this->_getPage('login', array('error'=>'Invalid token'));
                return $this;
            }
            
            if(empty($this->_request->login) || empty($this->_request->passwd))
            {
                $this->_user->reset();
                $this->_getPage('login', array('error'=>'Please fill all the fields.'));
                return $this;
            }
            elseif(mb_strlen($this->_request->login, 'UTF-8') > 55)
            {
                $this->_user->reset();
                $this->_getPage('login', array('error'=>'Invalid login or password. Please try again.'));
                return $this;
            }
            
            $this->_user->auth($this->_request->login, md5($this->_request->login.$this->_request->passwd));

            unset($this->_request->passwd);
        }
        
        $uid = $this->_user->getUid();

        if(!$uid)
        {
            $this->_user->reset();
            $this->_getPage('login', array('error'=>'Invalid login or password. Please try again.'));
            return $this;
        }
        
        if(!$auto)
        { // we set the session only if it is NOT a token login
        // because actions for this type of login is restricted and will always need
        // token and key passed by get or post
        // Also, we do not regenerate session id for case that the user already is logged
        // and is trying to log in again (for opensearch by example)
            if(!isset($isLogged) || !$isLogged)
            {
                $this->_sh->regenerateSessionId(true);
                $this->_user->reg(); // need to link back $_SESSION['User'] and the current user
                $this->_user->regenerateToken();
            }
            $this->redirect(isset($this->_request->back) ? $this->_request->back : null);
        }
        return $this;
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
        $this->_user->reset();
        $_SESSION = array();
        
        $session = session_name();
        $sessid = session_id();

        foreach(array('uid', 'tlogin', 'key', $session) as $name)
        {
            if(isset($_COOKIE[$name])) 
            {
                setcookie($name, '', $this->_request->begintime - 42000, $this->_cfg->get('path'), $this->_cfg->get('url'), $this->_cfg->get('httpsecure'), true);
            }
        }
        
        if($sessid) session_destroy();
        
        unset($sessid);
        if($redirect)
            $this->redirect('login');
        return $this;
    }

    /**
     * Moves a stream into another category
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_move()
    {
        Logic::getCachedLogic('streams')->move($this->_request);
        $this->processResponse($this->_request->getResponse());
        return $this;
    }

    /**
     * Deletes object(s)
     * If no id is specified, we deletes everything related to the user but not the user himself
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_delete()
    {
        if(!$this->_request->id)
        {
            Logic::getCachedLogic('users')->deleteRelated($this->_request);
            if(!$this->processResponse($this->_request->getResponse())) return $this;
        }
        else
        {
            $type = DAO::getType($this->_request->id);

            switch($type)
            {
                case 'users':
                    Logic::getCachedLogic('users')->delete($this->_request);
                    if(!$this->processResponse($this->_request->getResponse())) return $this;
                    $escape = true;
                    break;

                case 'news':
                case 'streams':
                case 'streams_groups':
                    $tpl = 'news';
                    Logic::getCachedLogic($type)->delete($this->_request);
                    if(!$this->processResponse($this->_request->getResponse())) return $this;
                    break;

                default:
                    throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
                    break;
            }
        }
        
        if(!isset($escape) && (!$this->_request->currentid || $this->_request->id === $this->_request->currentid))
        {
            $this->_getPage('news', array('id' => 0, 'sort' => $this->_request->sort ?: '', 'dir' => $this->_request->dir ?: ''));
        } 

        return $this;
    }
    
    /**
     * Adds a stream and redirects the user to the index
     * Used by externals call
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_add()
    {
        $this->do_editstream();
       
        $this->redirect();
        return $this;
    }
    
    /**
     * Adds a stream
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $url the url of the stream, optionnal
     * @param boolean $escapeNews must-we insert the parsed news ?
     * @access protected
     * @return $this
     */
    protected function do_editStream($url = null, $escapeNews = false)
    {
        $this->_request->url = $url ?: $this->_request->url;
        $this->_request->escapeNews = $escapeNews;
        $this->_request->escape = isset($url);

        Logic::getCachedLogic('streams')->edit($this->_request);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }
    
    /**
     * Adds/Edits a category
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $name the name of the category, optionnal
     * @access protected
     * @return $this
     */
    protected function do_editStreamGroup($name = null)
    {
        $this->_request->name = $name ?: $this->_request->name;
        $this->_request->new = false;
        $this->_request->escape = isset($name);

        Logic::getCachedLogic('streams_groups')->edit($this->_request);
        if(!$this->processResponse($this->_request->getResponse())) return $this;

        if(!isset($name))
        {
            $contents = array('id' => $this->_request->id);
            if($this->_request->new)
                $contents['menu'] = $this->_getPage('menu_part_category', array('gid'=>$this->_request->id, 'name'=>$this->_request->name), true);
            $this->_request->page = array();
            $this->addToPage($contents);
        }

        return $this;
    }
    
    /**
     * Adds streams from OPML input
     * If an url is passed, we'll try to get the opml remote file
     * else it is an uploaded file
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param $url the url of the OPML file, optional
     * @access protected
     * @return $this
     */
    protected function do_editOPML($url = null)
    {
        $this->_isFrame = true;
        $this->_request->url = $url ?: $this->_request->url;
        $this->_request->escape = isset($url);

        Logic::getCachedLogic('streams')->editOPML($this->_request);
        if(!$this->processResponse($this->_request->getResponse())) return $this;

        return $this;
    }

    /**
     * Renders REST auth tokens
     * We'll generate it if it does not exists
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_regenerateRESTAuthToken()
    {
        $tokensObj = DAO::getCachedDAO('users_tokens')->get('restauth', 'token AS tlogin, token_key AS tlogin_key');
        if(!$tokensObj)
        {
            $tokens = $this->_user->regenerateActionToken('restauth');
        }
        else $tokens = (array)$tokensObj;

        unset($tokensObj);
        $return = 'uid:'.$this->_user->getUid().';tlogin:'.$tokens['tlogin'].';key:'.$tokens['tlogin_key'];
        unset($tokens);
        $this->addToPage($return);
        return $this;
    }

    /**
     * Renders the stream gateway token
     * We'll generate it if it does not exists
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_regenerateRSSToken()
    {
        $tokensObj = DAO::getCachedDAO('users_tokens')->get($this->_request->do, 'token AS tlogin, token_key AS tlogin_key');
        if(!$tokensObj)
        {
            $tokens = $this->_user->regenerateActionToken('getrss');
        }
        else $tokens = (array)$tokensObj;

        unset($tokensObj);
        
        $url = $this->_cfg->get('surl').'?do=getrss&uid='.$this->_user->getUid().'&tlogin='.$tokens['tlogin'].'&key='.$tokens['tlogin_key'];
        unset($tokens);
        $this->addToPage($url);
        return $this;
    }

    /**
     * Renders an OPML gateway token
     * We'll generate it if it does not exists
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_regenerateOPMLToken()
    {
        $tokensObj = DAO::getCachedDAO('users_tokens')->get($this->_request->do, 'token AS tlogin, token_key AS tlogin_key');
        if(!$tokensObj)
        {
            $tokens = $this->_user->regenerateActionToken('getopml');
        }
        else $tokens = (array)$tokensObj;
        
        unset($tokensObj);
        
        $url = $this->_cfg->get('surl').'?do=getopml&uid='.$this->_user->getUid().'&tlogin='.$tokens['tlogin'].'&key='.$tokens['tlogin_key'];
        unset($tokens); 
        $this->addToPage($url);
        return $this;
    }

    /**
     * Tries to refresh stream(s)
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_refreshStream()
    {
        Logic::getCachedLogic('streams')->refresh($this->_request);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }
    

    /**
     * Update new(s) status (read/unread)
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $display must-we render something ?
     * @param string the name of the table corresponding to the specified id, optionnal
     * @access protected
     * @return $this
     */
    protected function do_upNew($display=true, $table='')
    {
        Logic::getCachedLogic('news')->update($this->_request);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }
    
    /**
     * Delete all news relations between the user and a specified stream/category
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_clearStream()
    {
        Logic::getCachedLogic('streams')->clear($this->_request);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }
    
    /**
     * Adds/Edits a user
     * Must be an administrator to add or edit another user
     * If no users are detected, we set the user automaticly as an administrator
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_editUser()
    {
        Logic::getCachedLogic('users')->edit($this->_request);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }

    /**
     * Renames a stream/category
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_rename()
    {
        if(!$this->_request->id)
        {
            $this->processResponse(new LogicResponse(array(
                'do'        => 'error',
                'error'     => 'Invalid id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }
        
        if(empty($this->_request->name))
        {
            $this->processResponse(new LogicResponse(array(
                'do'        => 'error',
                'error'     => 'Missing name',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $type = DAO::getType($this->_request->id);
        $obj = null;
        switch($type)
        {
            case 'streams':
                Logic::getCachedLogic('streams')->rename($this->_request);
                if(!$this->processResponse($this->_request->getResponse())) return $this;
                break;
            
            case 'streams_groups':
                Logic::getCachedLogic('streams_groups')->rename($this->_request);
                if(!$this->processResponse($this->_request->getResponse())) return $this;
                break;

            default:
                $this->processResponse(new LogicResponse(array(
                    'do'        => 'error',
                    'error'     => 'Invalid id',
                    'status'    => Exception::E_OWR_BAD_REQUEST
                )));
                break;
        }

        return $this;
    }
}
