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
    OWR\Model\Response as ModelResponse,
    OWR\View\Utilities;
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
 * @uses OWR\View\Utilities translate errors
 * @uses Theme the theme manager
 * @uses Dates the date manager
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
/*    protected $_dateFormatter;*/

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
            if(Exception::E_OWR_UNAUTHORIZED === $e->getCode())
                $this->redirect('login');

            throw new Exception($e->getContent(), Exception::E_OWR_UNAVAILABLE);
        }

        $this->_user = $this->_sh->get('User');
        if(!isset($this->_user) || !($this->_user instanceof User))
        {
            $this->_user = User::iGet();
            $this->_user->reg(); // populate into the session
        }

        Plugins::init();
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
            Plugins::pretrigger($this->_request);

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
                            throw new Exception(sprintf(Utilities::iGet()->_('Invalid action "%s"'), $this->_request->do), Exception::E_OWR_BAD_REQUEST);
                            break;
                    }

                    $this->do_login(true);
                }
                elseif($this->_request->do !== 'edituser' && $this->_request->do !== 'login')
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
                        $this->_buildPage('login', array('error' => Utilities::iGet()->_('You lost your token ! Confirm back your identity')));
                        return $this;
                    }
                }
                unset($token);
            }

            $action = 'do_'.$this->_request->do;

            if(!method_exists($this, $action)) // surely change this to a __call function to allow plugin
                Plugins::execute($this->_request);
        //        throw new Exception(sprintf(Utilities::iGet()->_('Invalid action "%s"'), $this->_request->do), Exception::E_OWR_BAD_REQUEST);

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

            Plugins::posttrigger($this->_request);

            // wait for all the threads for this action to ends properly if any
            $this->_wait();
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

        Plugins::trigger($this->_request);

        if(isset($_SERVER['HTTP_ACCEPT']) && (false !== strpos($_SERVER['HTTP_ACCEPT'], 'application/json')))
        {
            View::iGet()->addHeaders(array('Content-Type' => 'application/json; charset=utf-8'));
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

                    if(empty($page['errors'])) $page['errors'][] = Utilities::iGet()->_('Non-blocking error(s) occured');
                }
            }

            if(empty($page['errors']) && isset($_SERVER['REQUEST_METHOD']) && 'GET' === $_SERVER['REQUEST_METHOD'])
            {
                $etag = '"owr-'.md5(serialize($page)).'"';
                View::iGet()->addHeaders(array(
                    'Cache-Control' => 'Public, must-revalidate',
                    "Expires" => gmdate("D, d M Y H:i:s", $this->_request->begintime + $this->_cfg->get('cacheTime'))." GMT",
                    'Etag' => $etag
                ), true);
                if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
                {
                    View::iGet()->setStatusCode(304, true);
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
            if(empty($this->_request->page)) $this->_request->page = '';

            View::iGet()->addHeaders(array('Content-type' => 'text/html; charset=utf-8'));
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

                    if(empty($page['errors'])) $page['errors'][] = Utilities::iGet()->_('Non-blocking error(s) occured');

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
                            View::iGet()->addHeaders(array(
                                'Cache-Control' => 'Public, must-revalidate',
                                "Expires" => gmdate("D, d M Y H:i:s", $this->_request->begintime + $this->_cfg->get('cacheTime'))." GMT",
                                'Etag' => $etag
                            ), true);
                            if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
                            {
                                View::iGet()->setStatusCode(304, true);
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
                    View::iGet()->addHeaders(array(
                        'Cache-Control' => 'Public, must-revalidate',
                        "Expires" => gmdate("D, d M Y H:i:s", $this->_request->begintime + $this->_cfg->get('cacheTime'))." GMT",
                        'Etag' => $etag
                    ), true);
                    if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
                    {
                        View::iGet()->setStatusCode(304, true);
                        flush();
                        return $this;
                    }
                }
                if(empty($this->_request->page)) $this->_request->page = '';
                $now = microtime(true);
                $this->_request->page .= '<!-- Execution time: '.round($now - $this->_cfg->get('begintime'), 6).'s (Request time: '. round($now - $this->_request->begintime, 6).'s => '.round(DB::getTime(), 6).'s of SQL, '.round(View::getTime(), 6).'s of page rendering) -->';
            }

            $page =& $this->_request->page;
        }

        View::iGet()->setStatusCode($statusCode, true);

        View::iGet()->render($page);

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
                {
                    $current = preg_replace('/[?&]token=[^&]*/', '', $current); // strip the token, not needed
                    if(!empty($current) && $this->_cfg->get('path') !== $request)
                        $params .= '&back='.urlencode($current);
                }
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
            View::iGet()->render($page);
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
            View::iGet()->render($page);
        }
        exit;
    }

    /**
     * Process the response of a Model call
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param mixed ModelResponse the response of the
     */
    public function processResponse(ModelResponse $response)
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
                    $this->_buildPage($tpl, $response->getDatas());
                }
                else Logs::iGet()->log($response->getError(), $response->getStatus());
                $ret = false;
                break;

            case 'ok': // ok !
                $ret = true;
                $tpl = $response->getTpl();
                if($tpl)
                {
                    $this->_buildPage($tpl, $response->getDatas());
                }
                break;

            default:
                throw new Exception('Invalid return from Model', Exception::E_OWR_DIE);
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
        return Dates::format((int) $timestamp);

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
     * Adds a template to the page to display
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $tpl the name of the tpl, without the extension
     * @param array $datas the datas to apply to the template
     * @param boolean $return returns the template instead of rendering it
     * @return mixed the template if $return=true, else true
     * @access protected
     */
    protected function _buildPage($tpl, array $datas = array(), $return = false)
    {
        $noCacheDatas = array();
        
        $this->getPageDatas($tpl, $datas, $noCacheDatas);
        $this->_request->_datas = $datas;
        $this->_request->_noCacheDatas = $noCacheDatas;

        $page = Theme::iGet()->$tpl($datas, $noCacheDatas);
        
        if(!empty($page))
        {
            if($return)
                return $page;
            else
                $this->addToPage($page);
        }
    }

    /**
     * Waits for all threads to ends up properly if any,
     * or throw an 408 exception if we waited too long
     * to avoid having ghost processes
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     */
    protected function _wait()
    {
        $i = $sec = 0;
        $threads = Threads::iGet();
        while($threads->getQueueCount() && $sec <= 10)
        {
            if(!$threads->exec())
            { // have to wait a bit
                if($i > 5)
                {
                    $i = 0; // reset the timer every 1+2+3+4+5s
                    // security inc to not have infinite sleeping threads
                    // 150s will ends up with timeout
                    ++$sec;
                }
                sleep(++$i);
            }
            else
            {
                $i = $sec = 0; // resetting timer and inc
            }
        }

        if($threads->getQueueCount())
        { // we did not execute all threads, timeout
            throw new Exception(sprintf(Utilities::iGet()->_("Can not execute all threads for action \"%s\" : request timeout"), $action.'_'.$id), 408);
        }
    }

    /**
     * Methods bellow are actions to be executed by $this->execute()
     * They all are prefixed with do_*
     * @access protected
     * @return $this
     */

    /**
     * Now functions that do not require a model call
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
        View::iGet()->addHeaders(array('Content-Type' => 'text/xml; charset=utf-8'));
        $this->_buildPage('rss', array('id'=>$this->_request->id));
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
        $this->_buildPage('post_details', array('id' => $this->_request->id));
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
        $this->_buildPage('streams', array('id'=>$this->_request->id));
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
        $this->_buildPage('stream_details', array('id'=>$this->_request->id));
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

            if('streams' !== $type && 'streams_groups' !== $type && 'news_tags' !== $type)
                throw new Exception('Invalid Id', Exception::E_OWR_BAD_REQUEST);
        }

        if(isset($this->_request->status) && !empty($this->_request->ids))
        {
            $this->do_upNew();
        }

        $this->_buildPage('posts', array(
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

        $nb = DAO::getCachedDAO('news_relations')->count(array('status' => 1, 'FETCH_TYPE' => 'array'), 'newsid', 'tid', 'tid');
        if($nb)
        {
            if(is_array($nb[0]))
            {
                foreach($nb as $count)
                {
                    $unreads[$count[1]] = $count[0];
                }
            }
            else
            {
                $unreads[$nb[1]] = $nb[0];
            }
        }

        if(!$return)
            $this->_request->page = $unreads;
        else
            $this->_request->unreads = $unreads;

        $this->_user->setTimestamp();

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
        $this->_buildPage('index');
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
            throw new Exception("You don't have the rights to do that", Exception::E_OWR_UNAUTHORIZED);

        $this->_buildPage('users');
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
        View::iGet()->addHeaders(array('Content-Type' => 'text/xml; charset=utf-8'));
        $this->_buildPage('opensearch');
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
                $this->do_upNew();
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

        $this->_buildPage('post_content', array('id'=>$this->_request->id, 'offset'=>$this->_request->offset));
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
        if(!empty($this->_request->dl))
        {
            $opml = $this->_buildPage('opml', array('dateCreated'=>date("D, d M Y H:i:s T")), true);
            View::iGet()->addHeaders(array(
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
            View::iGet()->addHeaders(array('Content-Type' => 'text/xml; charset=UTF-8'));
            $this->_buildPage('opml', array('dateCreated'=>date("D, d M Y H:i:s T")));
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
            throw new Exception("You don't have the rights to do that", Exception::E_OWR_UNAUTHORIZED);

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
            SELECT id FROM news)
        AND id NOT IN (
            SELECT id FROM news_tags)';
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
        news_tags,
        news_relations,
        news_relations_tags,
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
            Logs::iGet()->log(Utilities::iGet()->_('Empty search, please enter at least one keyword'), Exception::E_OWR_BAD_REQUEST);
            $datas = array();
            $datas['sort'] = $this->_request->sort ?: '';
            $datas['dir'] = $this->_request->dir ?: '';
            $this->_buildPage('index');
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
            $query .= 'SELECT nr.newsid
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

                case 'news_tags':
                    $query .= '
                JOIN news_relations_tags nrt ON (nrt.newsid=nr.newsid)
                WHERE nr.uid='.$this->_user->getUid().' AND nrt.uid='.$this->_user->getUid().' AND tid='.$this->_request->id;
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
            $this->_buildPage('index', $datas);
        }
        else
        {
            Logs::iGet()->log(Utilities::iGet()->_('No results found. Try again by simplifying the request'), 204);
            $datas['sort'] = $this->_request->sort ?: '';
            $datas['dir'] = $this->_request->dir ?: '';
            $this->_buildPage('index', $datas);
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
            throw new Exception('Empty search, please enter at least one keyword', Exception::E_OWR_BAD_REQUEST);
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
            $query .= 'SELECT nr.newsid
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

                case 'news_tags':
                    $query .= '
                JOIN news_relations_tags nrt ON (nrt.newsid=nr.newsid)
                WHERE nr.uid='.$this->_user->getUid().' AND nrt.uid='.$this->_user->getUid().' AND tid='.$this->_request->id;
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
            $this->_buildPage('posts', $datas);
        }
        else
        {
            Logs::iGet()->log(Utilities::iGet()->_('No results found. Try again by simplifying the request'), 204);
        }
        return $this;
    }

    /**
     * Renders tags from a new
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getTags()
    {
        if(empty($this->_request->id))
            throw new Exception('Missing id', Exception::E_OWR_BAD_REQUEST);

        $this->_buildPage('post_tags', array('id' => $this->_request->id));
        return $this;
    }

    /**
     * Requires a call to a model from here
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
        Model::getCachedModel('users')->changeLang($this->_request);
        $this->processResponse($this->_request->getResponse());
        return $this;
    }

    /**
     * Returns a few statistics for current user
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_stats()
    {
        Model::getCachedModel('users')->stat($this->_request);
        $this->processResponse($this->_request->getResponse());
        return $this;
    }

    /**
     * Tries to auth user
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $auto automatic auth (gateway mode)
     * @access protected
     * @return $this
     */
    protected function do_login($auto=false)
    {
        $exists = DAO::getCachedDAO('users')->get(null, 'id', null, null, 1);

        if(!$exists)
        {
            $this->_user->reset();
            $this->_buildPage('user', array('id'=>0));
            return $this;
        }
        unset($exists);

        if(!$auto && empty($_POST))
        {
            $datas = array();
            if(isset($this->_request->timeout)) $datas['error'] = Utilities::iGet()->_('Session timeout');
            if(isset($this->_request->back)) $datas['back'] = $this->_request->back;
            $this->_user->reset();
            $this->_buildPage('login', $datas);
            return $this;
        }

        $uid = 0;

        if($auto)
        {
            if(!$this->_user->checkToken(true, $this->_request->uid, $this->_request->tlogin, $this->_request->key, $this->_request->do))
            {
                $this->_user->reset();
                $this->_buildPage('login', array('error' => Utilities::iGet()->_('Invalid token')));
                return $this;
            }
        }
        else
        {
            $isLogged = $this->_user->isLogged();
            if(!$this->_user->checkToken())
            {
                $this->_user->reset();
                $this->_buildPage('login', array('error' => Utilities::iGet()->_('Invalid token')));
                return $this;
            }

            if(empty($this->_request->login) || empty($this->_request->passwd))
            {
                $this->_user->reset();
                $this->_buildPage('login', array('error'=> Utilities::iGet()->_('Please fill all the fields.')));
                return $this;
            }
            elseif(mb_strlen($this->_request->login, 'UTF-8') > 55)
            {
                $this->_user->reset();
                $this->_buildPage('login', array('error' => Utilities::iGet()->_('Invalid login or password. Please try again.')));
                return $this;
            }

            $this->_user->auth($this->_request->login, md5($this->_request->login.$this->_request->passwd));

            unset($this->_request->passwd);
        }

        $uid = $this->_user->getUid();

        if(!$uid)
        {
            $this->_user->reset();
            $this->_buildPage('login', array('error' => Utilities::iGet()->_('Invalid login or password. Please try again.')));
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
        Model::getCachedModel('streams')->move($this->_request);
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
            Model::getCachedModel('users')->deleteRelated($this->_request);
            if(!$this->processResponse($this->_request->getResponse())) return $this;
        }
        else
        {
            $type = DAO::getType($this->_request->id);

            switch($type)
            {
                case 'users':
                    Model::getCachedModel('users')->delete($this->_request);
                    if(!$this->processResponse($this->_request->getResponse())) return $this;
                    $escape = true;
                    break;

                case 'news':
                case 'streams':
                case 'streams_groups':
                case 'news_tags':
                    Model::getCachedModel($type)->delete($this->_request);
                    if(!$this->processResponse($this->_request->getResponse())) return $this;
                    break;

                default:
                    throw new Exception('Invalid id', Exception::E_OWR_BAD_REQUEST);
                    break;
            }
        }

        if(!isset($escape) && (!$this->_request->currentid || $this->_request->id === $this->_request->currentid))
        {
            $this->_buildPage('posts', array('id' => 0, 'sort' => $this->_request->sort ?: '', 'dir' => $this->_request->dir ?: ''));
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

        Model::getCachedModel('streams')->edit($this->_request);
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

        Model::getCachedModel('streams_groups')->edit($this->_request);
        if(!$this->processResponse($this->_request->getResponse())) return $this;

        if(!isset($name))
        {
            $contents = array('id' => $this->_request->id);
            if($this->_request->new)
                $contents['menu'] = $this->_buildPage('category', array('gid'=>$this->_request->id, 'name'=>$this->_request->name), true);
            $this->_request->page = array();
            $this->addToPage($contents);
        }

        return $this;
    }


    /**
     * Renders the category template related to the specified id
     * If no id is specified, we render the default root category
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getStreamGroup()
    {
        $args = array();
        if(0 === $this->_request->id)
            $args['name'] = 'Root';
        Model::getCachedModel('streams_groups')->view($this->_request, $args);
        $response = $this->_request->getResponse();
        if(!$this->processResponse($response)) return $this;

        $datas = $response->getDatas();
        $this->_buildPage('category', array('gid'=>$datas['id'], 'name'=>$datas['name']));
        return $this;
    }

    /**
     * Adds streams from OPML input
     * If an url is passed, we'll try to get the remote opml file
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

        Model::getCachedModel('streams')->editOPML($this->_request);
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
        Model::getCachedModel('streams')->refresh($this->_request);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }

    /**
     * Update new(s) status (read/unread)
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_upNew()
    {
        Model::getCachedModel('news')->update($this->_request);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }

    /**
     * Delete all news relations between the user and a specified stream/category/tag
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_clearStream()
    {
        Model::getCachedModel('streams')->clear($this->_request);
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
        Model::getCachedModel('users')->edit($this->_request);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }

    /**
     * Adds/Edits a tag
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_editTag()
    {
        Model::getCachedModel('news_tags')->edit($this->_request);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }

    /**
     * Adds/removes tag(s) to new(s)
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_editTagsRelations()
    {
        Model::getCachedModel('news_tags')->editRelations($this->_request);
        $this->processResponse($this->_request->getResponse());

        return $this;
    }

    /**
     * Renames a stream/category/tag
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_rename()
    {
        if(!$this->_request->id)
        {
            $this->processResponse(new ModelResponse(array(
                'do'        => 'error',
                'error'     => 'Invalid id',
                'status'    => Exception::E_OWR_BAD_REQUEST
            )));
            return $this;
        }

        $type = DAO::getType($this->_request->id);
        $obj = null;
        switch($type)
        {
            case 'streams':
            case 'streams_groups':
            case 'news_tags':
                Model::getCachedModel($type)->rename($this->_request);
                if(!$this->processResponse($this->_request->getResponse())) return $this;
                break;

            default:
                $this->processResponse(new ModelResponse(array(
                    'do'        => 'error',
                    'error'     => 'Invalid id',
                    'status'    => Exception::E_OWR_BAD_REQUEST
                )));
                break;
        }

        return $this;
    }

    /**
     * Renders content of CLI logs file
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_getCLILogs()
    {
        if(!$this->_user->isAdmin())
            throw new Exception("You don't have the rights to do that", Exception::E_OWR_UNAUTHORIZED);

        $this->_buildPage('logs', array('logs' =>  Logs::iGet()->getCLILogs()));

        return $this;
    }

    /**
     * Retrieves datas for displaying template
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $tpl template name
     * @param array &$datas retrieved datas
     * @param array &$noCacheDatas retrieved not cached datas
     * @return $this
     */
    public function getPageDatas($tpl, array &$datas = array(), array &$noCacheDatas = array())
    {
        switch($tpl)
        {
            case 'post_content':
                $request = new Request(array('id' => $datas['id']));
                Model::getCachedModel('news')->view($request);
                $response = $request->getResponse();
                if('error' === $response->getNext())
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    break;
                }
                
                $datas = array_merge($datas, $response->getDatas());
            break;

            case 'post_details':
                $datas['details'] = array();
                $request = new Request(array('id' => $datas['id']));
                Model::getCachedModel('news')->view($request);
                $response = $request->getResponse();
                if('error' === $response->getNext())
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    break;
                }
                
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
            break;

            case 'category':
                $datas['gname'] = $datas['name'];
                $datas['groupid'] = $datas['gid'];

                if(empty($this->_request->unreads))
                    $this->do_getunread(true);

                $noCacheDatas['unread'] = isset($this->_request->unreads[$datas['gid']]) ? $this->_request->unreads[$datas['gid']] : 0;
                break;

            case 'streams':
                $streams = DAO::getDAO('streams_relations')->get(array('gid' => $datas['id']), 'rssid');
                if(!$streams)
                    break;

                if(empty($this->_request->unreads))
                    $this->do_getunread(true);
                if(is_object($streams))
                    $streams = array($streams);

                $request = new Request(array('id'=>null));
                Model::getCachedModel('streams_groups')->view($request, array(), 'name');
                $response = $request->getResponse();
                if('error' === $response->getNext())
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    break;
                }

                $datas['groups'] = $response->isMultiple() ? $response->getDatas() : array($response->getDatas());

                $request->getContents = false;
                foreach($streams as $s)
                {
                    $request->id = $s->rssid;
                    Model::getCachedModel('streams')->view($request);
                    $response = $request->getResponse();
                    if('error' === $response->getNext())
                    {
                        Logs::iGet()->log($response->getError(), $response->getStatus());
                        continue;
                    }
                    
                    $stream = $response->getDatas();
                    if(empty($stream))
                        break;

                    $stream['groups'] = $datas['groups'];
                    if($stream['status'] > 0)
                    {
                        $stream['unavailable'] = $this->_getDate($stream['status']);
                    }
                    $stream['unread'] = isset($this->_request->unreads[$stream['id']]) ? $this->_request->unreads[$stream['id']] : 0;
                    
                    $datas['streams'][] = $stream;
                }
                break;

            case 'post_tags':
                $datas['tags'] = array();
                $request = new Request(array(), true);
                Model::getCachedModel('news_tags')->view($request, array('newsid' => $datas['id']));
                $response = $request->getResponse();
                if('error' === $response->getNext())
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    break;
                }

                $tags = $response->getDatas();
                if(!empty($tags))
                    $datas['tags'] = $response->isMultiple() ? $tags : array($tags);
                break;

            case 'stream_details':
            case 'stream':
                $request = new Request(array('id' => $datas['id']));
                Model::getCachedModel('streams')->view($request);
                $response = $request->getResponse();
                if('error' === $response->getNext())
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    break;
                }
                
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);

                $datas = array_merge($datas, $response->getDatas());
                $noCacheDatas['unread'] = isset($this->_request->unreads[$datas['id']]) ? $this->_request->unreads[$datas['id']] : 0;
                break;

            case 'posts':
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);

                $datas['abstract'] = (bool) $this->_user->getConfig('abstract');
                    
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
                $nbNewsByPage = (int) $this->_user->getConfig('nbnews');

                if(isset($datas['offset']))
                {
                    $datas['offset'] = (int)$datas['offset'];

                    if($datas['offset'] > 0)
                    {
                        $offset = (int)($datas['offset'] * $nbNewsByPage);
                    }
                }
                else
                {
                    $datas['offset'] = 0;
                }

                $offset = $offset.','.$nbNewsByPage;

                if(isset($datas['id']) && is_array($datas['id']))
                {
                    if(empty($datas['id']))
                        break;
                    $request = new Request(array('ids' => $datas['id'], 'getContents' => $datas['abstract']));
                    Model::getCachedModel('news')->view($request, array(), $order, 'news.id', $offset);
                    $datas['nbNews'] = count($datas['id']);
                }
                elseif(empty($datas['id']))
                {
                    $request = new Request(array('id' => null, 'getContents' => $datas['abstract']));
                    Model::getCachedModel('news')->view($request, array('status' => 1), $order, 'news.id', $offset);
                    $datas['nbNews'] = isset($this->_request->unreads[0]) ? $this->_request->unreads[0] : 0;
                }
                elseif(-1 === $datas['id'])
                { // all news
                    $request = new Request(array('id' => null, 'getContents' => $datas['abstract']));
                    Model::getCachedModel('news')->view($request, array(), $order, 'news.id', $offset);
                    $nb = DAO::getCachedDAO('news_relations')->count(array(), 'newsid');
                    $datas['nbNews'] = $nb ? $nb->nb : 0;
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
                                break 2;
                            default: throw new Exception($e->getContent(), $e->getCode());
                                break;
                        }
                    }

                    $request = new Request(array('id' => null, 'getContents' => $datas['abstract']));
                    if('streams' === $table)
                    {
                        Model::getCachedModel('news')->view($request, array('rssid' => $datas['id']), $order, 'news.id', $offset);
                        $nb = DAO::getCachedDAO('news_relations')->count(array('rssid' => $datas['id']), 'newsid');
                        $datas['nbNews'] = $nb ? $nb->nb : 0;
                    }
                    elseif('streams_groups' === $table)
                    {
                        Model::getCachedModel('news')->view($request, array('gid' => $datas['id']), $order, 'news.id', $offset);
                        $nb = DAO::getCachedDAO('news_relations')->count(array('gid' => $datas['id']), 'newsid');
                        $datas['nbNews'] = $nb ? $nb->nb : 0;
                    }
                    elseif('news_tags' === $table)
                    {
                        Model::getCachedModel('news')->view($request, array('tid' => $datas['id']), $order, 'news.id', $offset);
                        $nb = DAO::getCachedDAO('news_relations_tags')->count(array('tid' => $datas['id']), 'newsid');
                        $datas['nbNews'] = $nb ? $nb->nb : 0;
                    }
                    else
                    {
                        Logs::iGet()->log(Utilities::iGet()->_('Invalid id'));
                        break;
                    }
                }

                $response = $request->getResponse();
                if('error' === $response->getNext())
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    break;
                }

                $datas['news'] = $response->getDatas();
                if(empty($datas['news']))
                    break;

                if(!$response->isMultiple()) $datas['news'] = array($datas['news']);

                $datas['pager'] = array('nbNews'         => (int) $datas['nbNews'],
                                'offset'        => $datas['offset'],
                                'sort'          => !empty($datas['sort']) ? $datas['sort'] : null,
                                'dir'           => !empty($datas['dir']) ? $datas['dir'] : null,
                                'nbNewsByPage'  => $nbNewsByPage);

                unset($datas['news']['ids']);
                                
                foreach($datas['news'] as $k => $new)
                {
                    if(isset($datas['searchResults'][$new['id']]))
                        $datas['news'][$k]['search_result'] = (float) $datas['searchResults'][$new['id']];
                    $datas['news'][$k]['pubDate'] = $this->_getDate($new['pubDate']);
                    $datas['news'][$k]['abstract'] = $datas['abstract'];
                }
            break;

            case 'index':
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);
                $datas['unreads'] = $this->_request->unreads;
                
                $this->getPageDatas('categories', $datas, $noCacheDatas);
                $this->getPageDatas('posts', $datas, $noCacheDatas);
                $this->getPageDatas('tags', $datas, $noCacheDatas);
                break;

            case 'categories':
                if(empty($this->_request->unreads))
                    $this->do_getunread(true);
                    
                $request = new Request(array('id' => null));
                Model::getCachedModel('streams_groups')->view($request, array(), 'name');
                $response = $request->getResponse();
                if('error' === $response->getNext())
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    break;
                }
                $groups = $response->getDatas();
                $datas['groups'] = array();
                if(!empty($groups))
                    $datas['groups'] = $response->isMultiple() ? $groups : array($groups);
                foreach($datas['groups'] as $k => $group)
                {
                    $datas['groups'][$k]['groupid'] = $group['id'];
                    $datas['groups'][$k]['gname'] = $group['name'];
                    $datas['groups'][$k]['unread'] = isset($this->_request->unreads[$group['id']]) ? $this->_request->unreads[$group['id']] : 0;
                }
                break;
            
            case 'tag':    
            case 'tags':
                $request = new Request(array('id' => isset($datas['id']) ? $datas['id'] : null, 'ids' => isset($datas['ids']) ? $datas['ids'] : null));
                Model::getCachedModel('news_tags')->view($request, array(), 'name');
                $response = $request->getResponse();
                if('error' !== $response->getNext())
                {
                    $tags = $response->getDatas();
                    $datas['tags'] = array();
                    if(!empty($tags))
                    {
                        $datas['tags'] = $response->isMultiple() ? $tags : array($tags);
                        foreach($datas['tags'] as $k => $tag)
                        {
                            $datas['tags'][$k]['groupid'] = $tag['id'];
                            $datas['tags'][$k]['gname'] = $tag['name'];
                            $datas['tags'][$k]['unread'] = isset($this->_request->unreads[$tag['id']]) ? $this->_request->unreads[$tag['id']] : 0;
                        }
                    }
                }
                else
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                }
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
                Model::getCachedModel('streams')->view($request);
                $response = $request->getResponse();
                if('error' === $response->getNext())
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    break;
                }

                $datas['streams'] = $response->isMultiple() ? $response->getDatas() : array($response->getDatas());
                break;

            case 'user':
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
                $datas['ulang'] = substr($this->_user->getLang(), 0, 2);
                $datas['xmlLang'] = $this->_user->getXMLLang();
                Model::getCachedModel('users')->view($request);
                $response = $request->getResponse();
                if('error' === $response->getNext())
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    break;
                }

                $datas = array_merge($datas, $response->getDatas());
                break;

            case 'users':
                $datas['surl'] = $this->_cfg->get('surl');
                $request = new Request($datas);
                Model::getCachedModel('users')->view($request, array(), 'login');
                $response = $request->getResponse();
                if('error' === $response->getNext())
                {
                    Logs::iGet()->log($response->getError(), $response->getStatus());
                    break;
                }

                $datas['users'] = $response->isMultiple() ? $response->getDatas() : array($response->getDatas());
                break;

            case 'rss':
//                 $request = new Request(array('id'=>null));
//                 $datas['surl'] = $this->_cfg->get('surl');
//                 $datas['userlogin'] = $this->_user->getLogin();
//                 $args = array('status' => 1);
//                 if(!empty($datas['id']))
//                     $args['rssid'] = $datas['id'];
//                 $datas['news'] = $ids = array();
//                 Model::getCachedModel('news')->view($request, $args);
//                 $response = $request->getResponse();
//                 if('error' === $response->getNext())
//                 {
//                     Logs::iGet()->log($response->getError(), $response->getStatus());
//                     break;
//                 }
// 
//                 $datas['news'] = $response->isMultiple() ? $response->getDatas() : array($response->getDatas());
// 
//                 foreach($datas['news'] as $new)
//                     $ids[] = $new['id'];
// 
//                 if(!empty($ids))
//                     $this->_db->set('
//     UPDATE news_relations
//         SET status=0
//         WHERE uid='.$this->_user->getUid().' AND newsid IN ('.join(',', $ids).')');
                break;

            case 'login':
                $noCacheDatas['back'] = $this->_request->back;
                break;

            default:
                break;
        }

        return $this;
    }
}
