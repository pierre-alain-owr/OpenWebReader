<?php
/**
 * Cron Controller class
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
 * @subpackage CLI
 */
namespace OWR\CLI;
use OWR\Controller as MainController,
    OWR\Config as Config,
    OWR\Stream\Parser as StreamParser,
    OWR\Stream\Reader as StreamReader,
    OWR\Stream\Item as StreamItem,
    OWR\OPML\Parser as OPMLParser,
    OWR\DB\Request as DBRequest,
    OWR\User as User,
    OWR\Logs as Logs,
    OWR\Request as Request,
    OWR\Exception as Exception,
    OWR\Error as Error,
    OWR\Cron as Cron,
    OWR\cURLWrapper as cURLWrapper,
    OWR\DAO as DAO,
    OWR\DB as DB,
    OWR\Logic as Logic;
if(!defined('INC_CONFIG')) die('Please include config file');
/**
 * This object is the front door of the application
 * @uses OWR\DAO deals with database
 * @uses OWR\Config the config instance
 * @uses OWR\Cron manages cron settings/locking (CLI mode or not)
 * @uses OWR\Controller parent controller
 * @uses OWR\DB the database link
 * @uses Request the request to execute
 * @uses OWR\Stream\Parser the stream parser
 * @uses OWR\Stream\Reader the stream reader
 * @uses OWR\Stream\Item the item reader
 * @uses User the current user
 * @uses Exception the exceptions handler
 * @uses OWR\Error the errors handler
 * @uses OWR\DB\Request a request sent to database
 * @uses cURLWrapper get favicon
 * @uses Logs the logs/errors storing object
 * @package OWR
 * @subpackage CLI
 */
class Controller extends MainController
{
    /**
     * Constructor, sets : all needed instances, session handler, errors and exceptions handler
     *
     * @access protected
     */
    protected function __construct()
    {
        if(!CLI) throw new Exception('CLI interface required', Exception::E_OWR_DIE);

        $this->_cfg = Config::iGet();

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
            $this->_db = DB::iGet(); // init DB connexion
        }
        catch(Exception $e)
        {
            throw new Exception($e->getContent(), Exception::E_OWR_UNAVAILABLE);
        }

        $this->_user = User::iGet(); // init user
    }
    
    /**
     * Executes the given action
     * This method only accepts a Request object
     * Throws a fatal Exception if something goes really wrong
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
            $id = $this->_request->id;

            $authorized = array(
                'refreshstream'=>true, 
                'managefavicons'=>true, 
                'checkstreamsavailability'=>true
            );
            if(!isset($authorized[$this->_request->do])) // hu ?
                throw new Exception('Invalid action "'.$this->_request->do.'"', Exception::E_OWR_BAD_REQUEST);
    
            $action = 'do_'.$this->_request->do;
    
            if(!method_exists($this, $action)) // surely change this to a __call function to allow plugin ?
                throw new Exception('Invalid action "'.$this->_request->do.'"', Exception::E_OWR_BAD_REQUEST);
        
            isset($this->_cron) || $this->_cron = Cron::iGet();

            if(!$id)
            {
                if($this->_cron->isLocked($action))
                {
                    $this->_cron->abort($action);
                    return $this;
                }
                // create a file that will lock the next processing of this cron job if the current is still running
                $this->_cron->lock($action);
            }

            $this->$action(); // execute the given action

            if(!$id)
                $this->_cron->unlock($action); // unlink file lock for next processing
        } 
        catch(Exception $e) 
        {
            if(!$id && isset($this->_cron))
                $this->_cron->unlock($action, true); // unlink file lock for next processing

            $this->_db->rollback();

            throw new Exception($e->getContent(), $e->getCode());
        }

        return $this;
    }

    /**
     * Render the page
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param int $statusCode HTTP status code, usefull for errors
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
        
        $hasErrors = false;

        if(Logs::iGet()->hasLogs())
        {
            Logs::iGet()->writeLogs();
        }

        if(!empty($this->_request->page))
            echo $this->_request->page;
        $this->_request->page = null;

        return $this;
    }

    /**
     * Redirects the user to a specific page
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $url the url to redirect
     * @access protected
     */
    protected function _redirect($url = null)
    {
        exit;
    }

    /**
     * Methods bellow are actions to be executen by $this->execute()
     * They all are prefixed with do_*
     * @access protected
     */

    /**
     * Checks for dead streams
     * CLI only
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_checkstreamsavailability()
    {
        Logic::getCachedLogic('streams')->checkAvailability($this->_request);
        $this->processResponse($this->_request->getResponse());
        return $this;
    }

    /**
     * Tries to get streams favicons
     * If you have Imagick extension installed, it will try to validate the icon
     * CLI only
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $redirect redirects the user to the login page
     * @access protected
     * @return $this
     */
    protected function do_managefavicons()
    {
        Logic::getCachedLogic('streams')->manageFavicons($this->_request);
        $this->processResponse($this->_request->getResponse());
        return $this;
    }

    /**
     * Tries to refresh stream(s)
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @return $this
     */
    protected function do_refreshstream()
    {
        Logic::getCachedLogic('streams')->refreshAllStreams($this->_request);
        $this->processResponse($this->_request->getResponse());
        return $this;
    }
}