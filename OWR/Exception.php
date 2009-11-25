<?php
/**
 * Errors/Exception handler class
 * This class is used to get errors and catch exceptions
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
use \Exception as Exceptions;
/**
 * This object is the exception handler
 * @package OWR
 */
class Exception extends Exceptions
{
    /**
    * @var int custom error type
    */
    const E_OWR_DIE = E_USER_ERROR;

    /**
    * @var int custom error type
    */
    const E_OWR_WARNING = E_USER_WARNING;

    /**
    * @var int custom error type
    */
    const E_OWR_NOTICE = E_USER_NOTICE;

    /**
    * @var int custom error type
    */
    const E_OWR_UNAUTHORIZED = 401;

    /**
    * @var int custom error type
    */
    const E_OWR_BAD_REQUEST = 400;

    /**
    * @var int custom error type
    */
    const E_OWR_UNAVAILABLE = 503;

    /**
    * @var array list of errors type
    * @access protected
    * @static
    */
    static protected $_type = array(
        self::E_OWR_DIE             => 'Fatal',
        self::E_OWR_WARNING         => 'Warning',
        self::E_OWR_NOTICE          => 'Notice',
        self::E_OWR_UNAUTHORIZED    => 'Unauthorized',
        self::E_OWR_BAD_REQUEST     => 'Bad Request',
        self::E_OWR_UNAVAILABLE     => 'Unavailable'
    );

    /**
     * Constructor
     * Will call Exception::__construct, send header if not already done
     * 
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $errstr the error message
     * @param int $errcode the error code
     */
    public function __construct($errstr, $errcode = self::E_OWR_DIE) 
    {
        parent::__construct($errstr, $errcode);
        
        switch($errcode)
        {
            case E_STRICT:
            case E_NOTICE:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_USER_NOTICE:
            case E_RECOVERABLE_ERROR:
            case E_CORE_WARNING:
            case E_WARNING:
            case E_USER_WARNING:
            case E_COMPILE_WARNING:
            case self::E_OWR_NOTICE:
                break;

            case 503:
            case 409:
            case 400:
            case 401:
            case 403:
                View::iGet()->setStatusCode($errcode, true);
                break;

            case E_USER_ERROR:
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case self::E_OWR_WARNING:
            case self::E_OWR_DIE:
            default:
                View::iGet()->setStatusCode(500, true);
                break;
        }
    }

    /**
     * Return the error message if debug, else a standard message
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    public function getContent()
    {
        $msg = "[".get_called_class().':'.$this->code.(isset(self::$_type[$this->code]) ? ':'.self::$_type[$this->code] : '').'] ';
        $msg .= $this->message.' in file '.$this->file;
        $msg .= ' on line '.$this->line;//.'. Stacktrace: '.$this->getTraceAsString();

        if(self::E_OWR_DIE === $this->code)
        {
            if(!DEBUG || !User::iGet()->isAdmin())
            {
                Logs::iGet()->log($msg, self::E_OWR_WARNING);
                $msg = $this->message;
            }
        }

        return $msg;
    }

    /**
     * Exception handler
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     * @param mixed $exception the exception object
     */
    static public function exception_handler($exception)
    {
        $msg = '['.(isset(self::$_type[$exception->getCode()]) ? self::$_type[$exception->getCode()] : 'unknown').'] ';
        $msg .= $exception->getMessage().' in file '.$exception->getFile().' on line '.$exception->getLine();
        
        if(!DEBUG || !User::iGet()->isAdmin())
        {
            Logs::iGet()->log($msg, $exception->getCode());
            $msg = $exception->getMessage();
        }

        switch($exception->getCode())
        {
            case E_STRICT:
            case E_NOTICE:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_USER_NOTICE:
            case E_RECOVERABLE_ERROR:
            case E_CORE_WARNING:
            case E_WARNING:
            case E_USER_WARNING:
            case E_COMPILE_WARNING:
            case self::E_OWR_NOTICE:
                break;

            case self::E_OWR_BAD_REQUEST:
                View::iGet()->setStatusCode(400, true);
                break;

            case self::E_OWR_UNAUTHORIZED:
                View::iGet()->setStatusCode(401, true);
                break;

            case self::E_OWR_UNAVAILABLE:
                View::iGet()->setStatusCode(503, true);
                break;

            case E_USER_ERROR:
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case self::E_OWR_WARNING:
            case self::E_OWR_DIE:
            default:
                View::iGet()->setStatusCode(500, true);
                break;
        }

        return true;
    }

    /**
     * Executed when the object is used as a string
     * Returns the contents of the exception
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    public function __toString() 
    {
        return $this->getContent();
    }
}
