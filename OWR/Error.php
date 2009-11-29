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
/**
 * This object is the error handler
 * @uses Exception the exception handler
 * @package OWR
 */
class Error extends Exception 
{
    /**
    * @var array list of errors type
    * @access protected
    * @static
    */
    static protected $_type = array(
        E_ERROR             => 'Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parse Error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_WARNING      => 'Internal Warning',
        E_USER_ERROR        => 'Internal Error',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Strict Error',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated'
    );

    /**
     * Constructor
     * Will call Exception::__construct, send header if not already done
     * 
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $errstr the error message
     * @param int $errno the error code
     */
    public function __construct($errstr, $errno) 
    {
        parent::__construct($errstr, $errno);
    }
    
    /**
     * Error handler
     * This function either throws an exception or just ignores the message if error level is lower than error code
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     * @param int $errno the error code
     * @param string $errstr the error message
     * @param string $errfile the file where the error occured
     * @param int $errline the line where the error occured
     * @return mixed true if not fatal
     */
    static public function error_handler($errno, $errstr='', $errfile='', $errline=0) 
    {
        // if error was triggered by @function
        // or error level is lower than error code
        // just ignore it
        if(($err = error_reporting()) === 0 || !($err & $errno)) 
        {
                return true;
        }

        $msg = '['.(isset(self::$_type[$errno]) ? self::$_type[$errno] : 'unknown').'] ';
        $msg .= $errstr.' in file '.$errfile.' on line '.$errline;

        if(!DEBUG && !User::iGet()->isAdmin())
        {
            Logs::iGet()->log($msg, $errno);
            $msg = $this->message;
        }

        switch($errno) 
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
                Logs::iGet()->log($msg, $errno);
                break;

            case E_USER_ERROR:
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            default:
                throw new Exception($msg, $errno);
                break;
        }

        return true;
    }
}