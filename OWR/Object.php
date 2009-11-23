<?php
/**
 * Object class
 * This class is usefull to convert everything to an object
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
 * This object is used to deal with objects instead of arrays
 * @package OWR
 */
class Object
{
    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $datas the values to convert
     */
    public function __construct(array $datas = array())
    {
        $this->_setDatas($datas);
    }

    /**
     * Getter of unexisting var
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @return null
     */
    public function __get($var)
    {
        $this->$var = null;
        return $this->$var;
    }

    /**
     * Setter of unexisting var
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @param mixed $value the value to set
     * @return boolean true
     */
    public function __set($var, $value)
    {
        return $this->_setDatas(array($var=>$value));
    }

    /**
     * Executed when this object is used as a string
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
     * Can be used to get multidimensional values
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @return mixed the var if exists, or null
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
     * Can be used to get multidimensional values
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

        isset($this->$v) || $this->$v = new Object;

        $datas = $this->$v;

        foreach($var as $arr)
        {
            isset($datas->$arr) || $datas->$arr = new Object;
            $datas = $datas->$arr;
        }

        if(is_object($value))
        {
            $datas = new Object(self::toArray($value));
        }
        elseif(is_array($value))
        {
            $datas = new Object($value);
        }
        else
        {
            $this->sanitize($value);
            $datas = $value;
        }

        return $value;
    }

    /**
     * Getter
     * Can be used to get multidimensional values
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @return mixed the var if exists, or null
     */
    protected function _setDatas(array $datas = array())
    {
        $this->sanitize($datas);

        foreach($datas as $k=>$data)
        {
            if(is_array($data))
            {
                $this->$k = new Object($data);
            }
            else
            {
                $this->$k = $data;
            }
        }

        return true;
    }

    /**
     * Abstract function to sanitize
     *
     * @access public
     * @abstract
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $datas the datas to sanitize 
     * @return mixed ?
     */
    public function sanitize(&$datas) {}

    /**
     * Returns public properties of current object as an associative array
     *
     * @access public
     * @return array the result
     */
    public function asArray()
    {
        $arr = array();

        foreach($this as $k=>$v)
        {
            if(is_object($v))
                $arr[$k] = self::toArray($v);
            else
                $arr[$k] = $v;
        }

        return $arr;
    }

    /**
     * Returns public properties of object passed by parameter as an associative array
     *
     * @access public
     * @static
     * @param mixed $object the object to convert
     * @return array the result
     */
    static public function toArray($object)
    {
        $arr = array();
        if(is_object($object))
        {
            foreach($object as $k=>$v)
            {
                if(is_object($v))
                    $arr[$k] = self::toArray($v);
                else $arr[$k] = $v;
            }
        }
        return $arr;
    }
}