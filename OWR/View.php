<?php
/**
 * View class
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
use OWR\View\Utilities as Utilities;
/**
 * This object is used to render page
 * @uses Singleton implements the singleton pattern
 * @uses Exception the exceptions handler
 * @uses Cache check cache directories
 * @uses OWR\View\Utilities templates tools
 * @package OWR
 */
class View extends Singleton
{
    /**
    * @var float rendering time
    * @access protected
    */
    static protected $_renderingTime = 0;

    /**
     * @var array list of headers
     * @access protected
     */
    protected $_headers = array();

    /**
     * @var mixed instance of OWR\View\Utilities
     * @access protected
     */
    protected $_utilities;

    /**
     * @var int HTTP status code
     * @access protected
     */
    protected $_statusCode = 200;

    /**
     * Constructor
     * Checks cache directories and set OWR\View\Utilities instance
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    protected function __construct()
    {
        Cache::checkDir(User::iGet()->getLang());
        $this->_utilities = Utilities::iGet();
    }

    /**
     * Returns the specified template with the specified datas
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $tpl the template name
     * @param array $datas the datas
     * @param boolean $xml is it XML ?
     * @param int $cacheTime cache time in seconds
     * @return string the template rendered
     */
    public function get($tpl, array $datas = array(), $xml = false, $cacheTime = 0)
    {
        $t = microtime(true);
        $cacheTime = (int) $cacheTime;
        if($cacheTime > 0)
        {
            $cachedTpl = HOME_PATH.'cache'.DIRECTORY_SEPARATOR.User::iGet()->getLang().DIRECTORY_SEPARATOR.md5($tpl.serialize($datas));
            if(file_exists($cachedTpl) && (@filemtime($cachedTpl) <= (int)(Config::iGet()->get('begintime') + $cacheTime)) && 
                ($f = @fopen($cachedTpl, 'rb')))
            {
                @flock($f, LOCK_SH);
                $contents = stream_get_contents($f);
                @flock($f, LOCK_UN);
                @fclose($f);
            }
        }

        if(!isset($contents))
        {
            $fulltpl = HOME_PATH.'tpl'.DIRECTORY_SEPARATOR.$tpl.'.html';
            extract($datas, EXTR_SKIP);
            ob_start();
            include $fulltpl;
            $contents = ob_get_clean();
            
            if($cacheTime > 0)
            {
                $f = @fopen($cachedTpl, 'w+b');
                if($f)
                {
                    @flock($f, LOCK_EX);
                    @fwrite($f, $contents);
                    @flock($f, LOCK_UN);
                    @fclose($f);
                }
            }
        }
        
        self::$_renderingTime += (float)microtime(true) - $t;
        
        return $contents;
    }

    /**
     * Returns the added microtime of all rendering
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed string or array to sanitize
     * @return float $_renderingTime
     * @static
     */
    static public function getTime()
    {
        return (float) self::$_renderingTime;
    }

    /**
     * Adds HTTP headers
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $headers associated array of HTTP header
     * @param boolean $send must-we send headers ?
     */
    public function addHeaders(array $headers, $send = false)
    {
        foreach($headers as $name=>$value)
        {
            $name = strtolower($name);
            isset($this->_headers[$name]) || $this->_headers[$name] = (string) $value;
        }

        if($send) $this->sendHeaders(false);
    }

    /**
     * Sets HTTP status code
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param int $code the code to set
     * @param boolean $send must-we send headers ?
     */
    public function setStatusCode($statusCode, $send = false)
    {
        $statusCode = (int) $statusCode;
        if($statusCode > $this->_statusCode)
            $this->_statusCode = $statusCode;

        if($send) $this->sendHeaders();
    }

    /**
     * Sends HTTP headers
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $sendStatus set to false to not send HTTP status code
     */
    public function sendHeaders($sendStatus = true)
    {
        if($sendStatus)
        {
            switch($this->_statusCode)
            {
                case 100: $statusCode = 'Continue'; break;
                case 101: $statusCode = 'Switching Protocols'; break;
                case 200: $statusCode = 'OK'; break;
                case 201: $statusCode = 'Created'; break;
                case 202: $statusCode = 'Accepted'; break;
                case 203: $statusCode = 'Non-Authoritative Information'; break;
                case 205: $statusCode = 'Reset Content'; break;
                case 206: $statusCode = 'Partial Content'; break;
                case 300: $statusCode = 'Multiple Choices'; break;
                case 301: $statusCode = 'Moved Permanently'; break;
                case 302: $statusCode = 'Found'; break;
                case 303: $statusCode = 'See Other'; break;
                case 304: $statusCode = 'Not Modified'; break;
                case 305: $statusCode = 'Use Proxy'; break;
                case 306: $statusCode = '(Unused)'; break;
                case 307: $statusCode = 'Temporary Redirect'; break;
                case 400: $statusCode = 'Bad Request'; break;
                case 401: $statusCode = 'Unauthorized'; break;
                case 402: $statusCode = 'Payment Required'; break;
                case 403: $statusCode = 'Forbidden'; break;
                case 404: $statusCode = 'Not Found'; break;
                case 405: $statusCode = 'Method Not Allowed'; break;
                case 406: $statusCode = 'Not Acceptable'; break;
                case 407: $statusCode = 'Proxy Authentication Required'; break;
                case 408: $statusCode = 'Request Timeout'; break;
                case 409: $statusCode = 'Conflict'; break;
                case 410: $statusCode = 'Gone'; break;
                case 411: $statusCode = 'Length Required'; break;
                case 412: $statusCode = 'Precondition Failed'; break;
                case 413: $statusCode = 'Request Entity Too Large'; break;
                case 414: $statusCode = 'Request-URI Too Long'; break;
                case 415: $statusCode = 'Unsupported Media Type'; break;
                case 416: $statusCode = 'Requested Range Not Satisfiable'; break;
                case 417: $statusCode = 'Expectation Failed'; break;
                case 501: $statusCode = 'Not Implemented'; break;
                case 502: $statusCode = 'Bad Gateway'; break;
                case 503: $statusCode = 'Service Unavailable'; break;
                case 504: $statusCode = 'Gateway Timeout'; break;
                case 505: $statusCode = 'HTTP Version Not Supported'; break;

                case 204: // we always return something
                    $this->_statusCode = 200;
                    $statusCode = 'OK';
                    break;

                case 500:
                default:
                    $this->_statusCode = 500;
                    $statusCode = 'Internal Server Error';
                    break;
            }

            if(!CLI) header('HTTP/1.1 '.$this->_statusCode.' '.$statusCode);
        }

        if(!CLI)
        {
            foreach($this->_headers as $name=>$value)
            {
                header($name.': '.$value);
            }
        }

        $this->_headers = array();
    }

    /**
     * Prints the page
     * This function tries to encode the contents
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $page the page to display
     */
    public function render($page)
    {
        $this->sendHeaders();
        // try to compress the page
        $encoding = false;
        if(extension_loaded('zlib') && !ini_get('zlib.output_compression'))
        {
            if(function_exists('ob_gzhandler') && @ob_start('ob_gzhandler'))
                $encoding = 'gzhandler';
            elseif(!headers_sent() && isset($_SERVER['HTTP_ACCEPT_ENCODING']))
            {
                if(mb_strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip', 0, 'UTF-8') !== false) 
                {
                    $encoding = 'x-gzip';
                } 
                elseif(mb_strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip', 0, 'UTF-8') !== false) 
                {
                    $encoding = 'gzip';
                }
            }
        }
        
        switch($encoding)
        {
            case 'gzhandler':
                @ob_implicit_flush(0);
                echo $page;
                @ob_end_flush();
                break;
            case 'gzip':
            case 'x-gzip':
                header('Content-Encoding: ' . $encoding);
                ob_start();
                echo $page;
                $page = ob_get_clean();
                $size = mb_strlen($page, 'UTF-8');
                $page = gzcompress($page, 6);
                $page = mb_substr($page, 0, $size, 'UTF-8');
                echo "\x1f\x8b\x08\x00\x00\x00\x00\x00";
            default:
                echo $page;
                flush();
                break;
        }
    }
}