<?php
/**
 * Object representing a request sent to the controller
 * This object is NOT designed to store objects, they will be automaticly transtyped to Request class
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
use OWR\Logic\Response as LogicResponse;
/**
 * This object is sent to the Controller to be executed
 * @uses String convert M$ bad chars
 * @package OWR
 */
class Request
{
    /**
    * @var mixed the Filter instance
    * @access protected
    * @static
    */
    static protected $_filter;

    /**
    * @var mixed the Logic\Response instance
    * @access protected
    */
    protected $_response;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $datas the datas
     * @param boolean $nodatas if we must NOT get datas from GET/POST if $datas is empty
     */
    public function __construct(array $datas = array(), $nodatas = false)
    {
        if(empty($datas) && false === $nodatas)
        {
            // post
            if(!empty($_POST))
            {
                foreach($_POST as $k => &$v)
                {
                    $datas[$k] = &$v;
                }
            }
            
            // get
            if(!empty($_GET))
            {
                foreach($_GET as $k => &$v)
                {
                    (!isset($datas[$k]) && $datas[$k] = &$v) || $_GET[$k] = null;
                }
            }
        }

        $this->page = '';
        unset($datas['page']);

        foreach(array('id', 'gid', 'currentid', 'uid', 'offset', 'timestamp', 'live') as $k)
        {
            (isset($datas[$k]) && $this->$k = (int) $datas[$k]) || $this->$k = 0;
            unset($datas[$k]);
        }

        $this->ids = array();

        if(isset($datas['ids']))
        {
            if(is_array($datas['ids']))
            {
                foreach($datas['ids'] as $k=>$id)
                    $this->ids[$k] = (int) $id;
            }

            unset($datas['ids']);
        }

        (isset($datas['do']) && $this->do = mb_strtolower((string)$datas['do'], 'UTF-8')) || $this->do = 'index';

        unset($datas['do']);

        if(isset($datas['sort']))
        {
            $authorized = array('title'=>'n', 'pubDate'=>'n', 'status'=>'nr');
            (isset($authorized[$datas['sort']]) && $this->sort = $authorized[$datas['sort']].'.'.$datas['sort']);
            unset($datas['sort']);

            if(isset($datas['dir']) && $this->sort)
            {
                $datas['dir'] = (string) $datas['dir'];
                (($datas['dir'] === 'desc' || $datas['dir'] === 'asc') && $this->dir = $datas['dir']) || $this->dir = 'asc';
                unset($datas['dir']);
            }
            else $this->dir = 'asc';
        }

        (!isset($datas['back']) || (!isset($_SERVER['X_REQUESTED_WITH']) || $_SERVER['X_REQUESTED_WITH'] !== 'XMLHttpRequest')) 
        || $datas['back'] = urldecode((string) $datas['back']);

        !isset($datas['token']) || $this->token = (string) $datas['token'];

        unset($datas['token']);

        $this->_setDatas($datas);
        unset($datas);

        (!empty($this->lang) && $this->lang = (string) $this->lang) ||
        (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) && $this->lang = (string)$_SERVER['HTTP_ACCEPT_LANGUAGE']) ||
        $this->lang = Config::iGet()->get('default_language');
    }

    /**
     * Getter for unexisting var
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @return null
     */
    public function __get($var)
    {
//         $this->$var = null;
//         return $this->$var;
        return null;
    }

    /**
     * Setter for unexisting var
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @param mixed $value the value to assign to $var
     * @return mixed the value
     */
//     public function __set($var, $value)
//     {
//         return $this->_setDatas(array($var=>$value));
//     }

    /**
     * Called when using this object as a string
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return string this object serialized
     */
    public function __toString()
    {
        return serialize($this);
    }

    /**
     * Getter
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @return mixed null if exists, or the value
     */
    public function get($var)
    {
        $var = (string) $var;

        if(false === strpos($var, '.'))
        {
            return $this->$var;
        }

        $var = explode('.', $var);

        $v = array_shift($var);

        if(!isset($this->$v)) return null;

        $datas = $this->$v;

        foreach($var as $arr)
        {
            if(!isset($datas->$arr)) return null;
            $datas = $datas->$arr;
        }

        return $datas;
    }

    /**
     * Setter
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @param mixed $value the value to assign to $var
     * @return mixed the value
     */
    public function set($var, $value)
    {
        $var = (string) $var;

        if(false === strpos($var, '.'))
        {
            return ($this->_setDatas(array($var=>$value)));
        }

        $var = explode('.', $var);

        $v = array_shift($var);

        isset($this->$v) || $this->$v = new Request(array(), true);

        $datas = $this->$v;

        foreach($var as $arr)
        {
            isset($datas->$arr) || $datas->$arr = new Request(array(), true);
            $datas = $datas->$arr;
        }

        if(is_array($value))
        {
            $datas = new Request($value);
        }
        elseif(is_object($value))
        {
            $datas = new Request(Object::toArray($value));
        }
        else
        {
            static::sanitize($value);
            $datas = $value;
        }

        return $value;
    }

    /**
     * Sets the response of a Logic call
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $response the Logic\Response instance
     */
    public function setResponse(LogicResponse $response)
    {
        $this->_response = $response;
    }

    /**
     * Returns the response of a Logic call
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return mixed $response the Logic\Response instance
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * Sanitize function
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $datas the datas to sanitize
     */
    static public function sanitize(&$datas)
    {
        $isArray = $isObject = $isString = false;
        if(is_null($datas) || ((!($isString = is_string($datas))) && 
            (!($isArray = is_array($datas))) &&
            (!($isObject = is_object($datas)))))
        {
            return;
        }

        if($isString)
        {
            isset(self::$_filter) || self::$_filter = Filter::iGet();
            $datas = trim($datas);
            !get_magic_quotes_gpc() || ($datas = stripslashes($datas));
            $datas = Strings::toNormal($datas);
            $datas = self::$_filter->purify($datas);
        }
        elseif($isObject)
        {
            $datas = Object::toArray($datas);
            array_walk_recursive($datas, array('static', 'sanitize'));
        }
        elseif($isArray)
        {
            array_walk_recursive($datas, array('static', 'sanitize'));
        }
    }

    /**
     * Sanitizes and set datas
     *
     * @access private
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $datas the datas to set
     */
    protected function _setDatas(array $datas = array())
    {
        if(empty($datas)) return true;

        static::sanitize($datas);

        foreach($datas as $k=>$data)
        {
            if(is_array($data))
            {
                $this->$k = new Request($data);
            }
            else
            {
                $this->$k = $data;
            }
        }

        return true;
    }
}