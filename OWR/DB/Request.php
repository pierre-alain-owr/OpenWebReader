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
use \ArrayObject,
    OWR\Exception,
    OWR\User,
    OWR\View\Utilities;
/**
 * This object is sent to DB to be executed
 * @uses OWR\Exception the exceptions handler
 * @uses OWR\View\Utilities translate errors
 * @uses OWR\User check timezones and rights
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
     * @var int type representing a null value
     */
    const PARAM_NULL = 1121;

    /**
     * @var int type representing a serialized value
     */
    const PARAM_SERIALIZED = 1122;

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
        $i=0;

        foreach($fields as $k => $v)
        {
            if(!isset($v['type']))
            {
                foreach($v as $key=>$value)
                {
                    if(isset($datas[$k][$key]))
                    {
                        $request[++$i] = $this->_getParameter($datas[$k][$key], $k, $value);
                    }
                    elseif(!$value['required'])
                    {
                        ++$i;
                        $request[$i]['type'] = $value['type'];
                        if(isset($value['default']))
                        { // here we assume that the default value is a GOOD value, be carefull
                            $request[$i]['value'] = $value['default'];
                        }
                        else
                        {
                            switch($value['type'])
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

                                case self::PARAM_SERIALIZED: $request[$i]['value'] = serialize(array()); $request[$i]['type'] = \PDO::PARAM_STR; break;

                                case \PDO::PARAM_NULL :
                                case \PDO::PARAM_LOB :
                                case \PDO::PARAM_STMT:
                                case self::PARAM_NULL:
                                default : $request[$i]['value'] = null; break;
                            }
                        }
                    }
                    elseif(!$force)
                    {
                        throw new Exception('Missing required parameter "'.$k.'"', Exception::E_OWR_WARNING);
                    }
                }
            }
            else
            {
                if(isset($datas[$k]))
                {
                    $request[++$i] = $this->_getParameter($datas[$k], $k, $v);
                }
                elseif(!$v['required'])
                {
                    ++$i;
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

                            case self::PARAM_SERIALIZED: $request[$i]['value'] = serialize(array()); $request[$i]['type'] = \PDO::PARAM_STR; break;

                            case \PDO::PARAM_NULL :
                            case \PDO::PARAM_LOB :
                            case \PDO::PARAM_STMT:
                            case self::PARAM_NULL:
                            default : $request[$i]['value'] = null; break;
                        }
                    }
                }
                elseif(!$force)
                {
                    throw new Exception(sprintf(Utilities::iGet()->_('Missing required parameter "%s"'), $k), Exception::E_OWR_WARNING);
                }
            }
        }

        return $request;
    }

    /**
     * Valid the contents of a parameter following the field definition
     *
     * @access private
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $datas the datas
     * @param string $name the name of the field
     * @param array $field the fields
     * @return array the param on success
     */
    private function _getParameter($datas, $name, $field)
    {
        switch($field['type'])
        { // internal check, can't be checked by DB
            case self::PARAM_EMAIL:
                $datas = (string) $datas;
                if('' !== $datas)
                {
                    $datas = filter_var($datas, FILTER_VALIDATE_EMAIL);
                    if(false === $datas)
                        throw new Exception(sprintf(Utilities::iGet()->_('Invalid email for field "%s"'), $name), Exception::E_OWR_WARNING);
                }
                $type = \PDO::PARAM_STR;
                break;

            case self::PARAM_URL:
                $datas = (string) $datas;
                if('' !== $datas)
                {
                    $validUrl = filter_var($datas, FILTER_VALIDATE_URL);
                    if(false === $validUrl)
                    {
                        $url = @parse_url($datas);
                        if(isset($url['scheme']) && isset($url['path']) && isset($url['host']))
                        {
                            $url['path'] = join('/', array_map('rawurlencode', explode('/', $url['path'])));
                            $url = $url['scheme'].'://'.$url['host'].$url['path'];
                            $validUrl = filter_var($url, FILTER_VALIDATE_URL);
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
                        throw new Exception(sprintf(Utilities::iGet()->_('Invalid url for field "%s"'), $name), Exception::E_OWR_WARNING);
                    $datas = $validUrl;
                    unset($url, $validUrl);
                }
                $type = \PDO::PARAM_STR;
                break;

            case self::PARAM_IP:
                $datas = (string) $datas;
                if('' !== $datas)
                {
                    $datas = filter_var($datas, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
                    if(false === $datas)
                        throw new Exception(sprintf(Utilities::iGet()->_('Invalid IP address for field "%s"'), $name), Exception::E_OWR_WARNING);
                }
                $type = \PDO::PARAM_STR;
                break;

            case self::PARAM_LANG:
                $datas = (string) $datas;
                if('' !== $datas)
                {
                    $datas = filter_var($datas, FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/^[a-z]{2,3}_[A-Z]{2,3}$/")));
                    if(false === $datas)
                        throw new Exception(sprintf(Utilities::iGet()->_('Invalid lang for field "%s"'), $name), Exception::E_OWR_WARNING);
                }
                $type = \PDO::PARAM_STR;
                break;

            case self::PARAM_CURRENT_TIMESTAMP:
                $datas = (int) $datas;
                $type = \PDO::PARAM_INT;
                break;

            case self::PARAM_LOGIN:
                $datas = (string) $datas;
                if('' !== $datas)
                {
                    $datas = filter_var($datas);
                    if(false === $datas || mb_strlen($datas, 'UTF-8') > 55)
                        throw new Exception(sprintf(Utilities::iGet()->_('Invalid login for field "%s"'), $name), Exception::E_OWR_WARNING);
                }
                $type = \PDO::PARAM_STR;
                break;

            case self::PARAM_PASSWD:
                $datas = (string) $datas;
                if('' !== $datas)
                {
                    $datas = filter_var($datas);
                    if(false === $datas || mb_strlen($datas, 'UTF-8') !== 32) // we are asking md5 string, 32 chars
                        throw new Exception(sprintf(Utilities::iGet()->_('Invalid password for field "%s"'), $name), Exception::E_OWR_WARNING);
                }
                $type = \PDO::PARAM_STR;
                break;

            case self::PARAM_RIGHTS:
                $datas = (int) $datas;
                if($datas > User::LEVEL_ADMIN || $datas < User::LEVEL_VISITOR)
                    throw new Exception(sprintf(Utilities::iGet()->_('Invalid rights for field "%s"'), $name), Exception::E_OWR_WARNING);
                $type = \PDO::PARAM_INT;
                break;

            case self::PARAM_TIMEZONE:
                $datas = User::iGet()->getTimezones($datas);
                $type = \PDO::PARAM_STR;
                break;

            case self::PARAM_HASH:
                $datas = (string) $datas;
                if('' !== $datas)
                {
                    $datas = filter_var($datas);
                    if(false === $datas || mb_strlen($datas, 'UTF-8') !== 32) // we are asking md5 string, 32 chars
                        throw new Exception(sprintf(Utilities::iGet()->_('Invalid hash for field "%s"'), $name), Exception::E_OWR_WARNING);
                }
                $type = \PDO::PARAM_STR;
                break;

            case self::PARAM_SERIALIZED:
                $datas = empty($datas) ? null : serialize((array) $datas);
                $type = \PDO::PARAM_STR;
                break;

            default:
                $type = $field['type'];
                break;
        }

        if(!$datas && $field['required'])
        {
            if(!empty($field['default']))
                $datas = $field['default'];
            else
                throw new Exception(sprintf(Utilities::iGet()->_('Missing value for required parameter "%s"'), $name), Exception::E_OWR_WARNING);
        }

        return array('type' => $type, 'value' => $datas);
    }
}
