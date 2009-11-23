<?php
/**
 * Object representing a request sent to the DB class
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
 * @subpackage DB
 */
namespace OWR\DB;
use \ArrayObject as ArrayObject,
    OWR\Exception as Exception,
    OWR\User as User;
/**
 * This object is sent to DB to be executed
 * @uses Exception the exceptions handler
 * @package OWR
 * @subpackage DB
 */
class Request extends ArrayObject
{
    /**
    * @var int type representing the current timestamp (= time())
    */
    const PARAM_CURRENT_TIMESTAMP = 1111;

    /**
    * @var int type representing a hash (= md5())
    */
    const PARAM_HASH = 1112;

    /**
    * @var int type representing an email
    */
    const PARAM_EMAIL = 1113;

    /**
    * @var int type representing an url
    */
    const PARAM_URL = 1114;

    /**
    * @var int type representing an IP address
    */
    const PARAM_IP = 1115;

    /**
    * @var int type representing a lang
    */
    const PARAM_LANG = 1116;

    /**
    * @var int type representing a login
    */
    const PARAM_LOGIN = 1117;

    /**
    * @var int type representing a password
    */
    const PARAM_PASSWD = 1118;

    /**
    * @var int type representing a user rights
    */
    const PARAM_RIGHTS = 1119;

    /**
    * @var int type representing a timezone
    */
    const PARAM_TIMEZONE = 1120;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $datas the datas
     * @param array $fields the fields
     * @param boolean $force used to specify that we just check value (and not if empty)
     */
    public function __construct(array $datas, array $fields = array(), $force = false)
    {
        parent::__construct($this->_makeRequest($datas, $fields, $force));
    }

    /**
     * Checks, sanitizes and sets the datas
     *
     * @access private
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $datas the datas
     * @param array $fields the fields
     * @param boolean $force used to specify that we just check value (and not if empty)
     * @return array the request
     */
    private function _makeRequest(&$datas, &$fields, $force)
    {
        if(empty($fields)) return array_values($datas);
        $request = array();
        $i=1;
        foreach($fields as $k=>$v)
        {
            if(isset($datas[$k]))
            {
                switch($v['type'])
                { // internal check, can't be checked by DB
                    case self::PARAM_EMAIL:
                        $datas[$k] = (string) $datas[$k];
                        if('' !== $datas[$k])
                        {
                            $datas[$k] = filter_var($datas[$k], FILTER_VALIDATE_EMAIL);
                            if(false === $datas[$k])
                                throw new Exception('Aborting SQLRequest::_makeRequest, Invalid email for field '.$k, Exception::E_OWR_WARNING);
                        }
                        $request[$i]['type'] = \PDO::PARAM_STR;
                        break;

                    case self::PARAM_URL:
                        $datas[$k] = (string) $datas[$k];
                        if('' !== $datas[$k])
                        {
                            $validUrl = filter_var($datas[$k], FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED);
                            if(false === $validUrl)
                            {
                                $url = @parse_url($datas[$k]);
                                if(isset($url['scheme']) && isset($url['path']) && isset($url['host']))
                                {
                                    $url['path'] = join('/', array_map('rawurlencode', explode('/', $url['path'])));
                                    $url = $url['scheme'].'://'.$url['host'].$url['path'];
                                    $validUrl = filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED);
                                }
                            }
                            
                            if($validUrl)
                            {
                                $scheme = @parse_url($validUrl, PHP_URL_SCHEME);
                                if(!$scheme || 'file' === $scheme)
                                    $validUrl = false;
                                unset($scheme);
                            }
    
                            if(false === $validUrl)
                                throw new Exception('Aborting SQLRequest::_makeRequest, Invalid url for field '.$k, Exception::E_OWR_WARNING);
                            $datas[$k] = $validUrl;
                            unset($url, $validUrl);
                        }
                        $request[$i]['type'] = \PDO::PARAM_STR;
                        break;

                    case self::PARAM_IP: 
                        $datas[$k] = (string) $datas[$k];
                        if('' !== $datas[$k])
                        {
                            $datas[$k] = filter_var($datas[$k], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
                            if(false === $datas[$k])
                                throw new Exception('Aborting SQLRequest::_makeRequest, Invalid IP address for field '.$k, Exception::E_OWR_WARNING);
                        }
                        $request[$i]['type'] = \PDO::PARAM_STR;
                        break;

                    case self::PARAM_LANG:
                        $datas[$k] = (string) $datas[$k];
                        if('' !== $datas[$k])
                        {
                            $datas[$k] = filter_var($datas[$k], FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/^[a-z]{2,3}_[A-Z]{2,3}$/")));
                            if(false === $datas[$k])
                                throw new Exception('Aborting SQLRequest::_makeRequest, Invalid lang for field '.$k, Exception::E_OWR_WARNING);
                        }
                        $request[$i]['type'] = \PDO::PARAM_STR;
                        break;

                    case self::PARAM_CURRENT_TIMESTAMP:
                        $datas[$k] = (int) $datas[$k];
                        $request[$i]['type'] = \PDO::PARAM_INT;
                        break;

                    case self::PARAM_LOGIN:
                        $datas[$k] = (string) $datas[$k];
                        if('' !== $datas[$k])
                        {
                            $datas[$k] = filter_var($datas[$k]);
                            if(false === $datas[$k] || mb_strlen($datas[$k], 'UTF-8') > 55)
                                throw new Exception('Aborting SQLRequest::_makeRequest, Invalid login for field '.$k, Exception::E_OWR_WARNING);
                        }
                        $request[$i]['type'] = \PDO::PARAM_STR;
                        break;

                    case self::PARAM_PASSWD:
                        $datas[$k] = (string) $datas[$k];
                        if('' !== $datas[$k])
                        {
                            $datas[$k] = filter_var($datas[$k]);
                            if(false === $datas[$k] || mb_strlen($datas[$k], 'UTF-8') !== 32) // we wait md5 string, 32 chars
                                throw new Exception('Aborting SQLRequest::_makeRequest, Invalid password for field '.$k, Exception::E_OWR_WARNING);
                        }
                        $request[$i]['type'] = \PDO::PARAM_STR;
                        break;

                    case self::PARAM_RIGHTS:
                        $datas[$k] = (int) $datas[$k];
                        if($datas[$k] > User::LEVEL_ADMIN || $datas[$k] < User::LEVEL_VISITOR)
                            throw new Exception('Aborting SQLRequest::_makeRequest, Invalid rights for field '.$k, Exception::E_OWR_WARNING);
                        $request[$i]['type'] = \PDO::PARAM_STR;
                        break;

                    case self::PARAM_TIMEZONE:
                        $datas[$k] = User::iGet()->getTimezones($datas[$k]);
                        $request[$i]['type'] = \PDO::PARAM_STR;
                        break;

                    case self::PARAM_HASH:
                        $datas[$k] = (string) $datas[$k];
                        $request[$i]['type'] = \PDO::PARAM_STR;
                        break;

                    default: 
                        $request[$i]['type'] = $v['type'];
                        break;
                }

                if(!$datas[$k] && !$force && $v['required'])
                {
                    throw new Exception('Aborting SQLRequest::_makeRequest, missing value for required parameter "'.$k.'"', Exception::E_OWR_WARNING);
                }

                $request[$i]['value'] = $datas[$k];
                ++$i;
            }
            elseif(!$v['required'] && !$force)
            {
                $request[$i]['type'] = $v['type'];
                if(isset($v['default']))
                { // here we assume that the default value is a GOOD value, be carefull
                    $request[$i]['value'] = $v['default'];
                }
                else
                {
                    switch($v['type'])
                    {
                        case \PDO::PARAM_INT : $request[$i]['value'] = 0; break;
                        case \PDO::PARAM_STR : $request[$i]['value'] = ''; break;
                        case \PDO::PARAM_BOOL : $request[$i]['value'] = false; break;
                        
                        case self::PARAM_CURRENT_TIMESTAMP : $request[$i]['value'] = time(); $request[$i]['type'] = \PDO::PARAM_INT; break;
                        case self::PARAM_EMAIL :
                        case self::PARAM_URL :
                        case self::PARAM_IP :
                        case self::PARAM_LANG :
                        case self::PARAM_LOGIN :
                        case self::PARAM_HASH : $request[$i]['value'] = ''; $request[$i]['type'] = \PDO::PARAM_STR; break;
                        
                        case \PDO::PARAM_NULL :
                        case \PDO::PARAM_LOB : 
                        case \PDO::PARAM_STMT:
                        default : $request[$i]['value'] = null; break;
                    }
                }
                ++$i;
            }
            elseif(!$force)
            {
                throw new Exception('Aborting SQLRequest::_makeRequest, Missing required parameter "'.$k.'"', Exception::E_OWR_WARNING);
            }
        }

        return $request;
    }
}