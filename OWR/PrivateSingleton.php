<?php
/**
 * Abstract class for singleton pattern in private mode
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
 * @abstract
 */
namespace OWR;
use OWR\Interfaces\PrivateSingleton as iPrivateSingleton;
/**
 * This object is used to implement the singleton pattern
 * It is designed to store read-only values
 * @abstract
 * @package OWR
 */
abstract class PrivateSingleton implements iPrivateSingleton
{
    /**
    * @var array store instancied objects
    * @access private
    * @static
    */
    private static $_instances = array();

    /**
     * @var array the stored values
     * @access protected
     */
    protected $_datas = array();

    /**
     * Constructor
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $datas the stored values
     */
    protected function __construct(array $datas)
    {
        empty($datas[0]) || $this->_datas = $datas[0];
    }

    /**
     * Instance getter
     * This function can NOT be overloaded
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return mixed the instance
     */
    final static public function iGet() 
    {
        $c = get_called_class();
        if(!isset(self::$_instances[$c]))
        {
            self::$_instances[$c] = new $c(func_get_args());
        }

        return self::$_instances[$c];
    }

    /**
     * Cloning is denied
     * This function can NOT be overloaded
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    final public function __clone()
    {
        throw new Exception('Please edit your code, cloning a singleton is not allowed');
    }

    /**
     * Executed when an object is unserialized
     * We register the new object
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __wakeUp()
    {
        self::register($this);
    }

    /**
     * Register function
     * This function is called when an object is unserialized and is used to register the instance of the object
     * This function can NOT be overloaded
     *
     * @access public
     * @static
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    final static public function register($instance)
    {
        if(!is_object($instance)) return false;

        $c = get_class($instance);
        if(isset(self::$_instances[$c]))
            return false;

        self::$_instances[$c] = $instance;
    }

    /**
     * Executed when trying to get values without calling the appropriate function
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @return mixed the desired value if exists or null
     */
    public function __get($var)
    {
        return $this->get($var);
    }

    /**
     * Executed when trying to set values without calling the appropriate function
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @param mixed $value the value to assign to $var
     * @return mixed the value if succeed of false
     */
    public function __set($var, $value)
    {
        return $this->set($var, $value);
    }

    /**
     * Getter
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @return mixed the desired value if exists or null
     */
    public function get($var)
    {
        $var = (string) $var;

        if(false === strpos($var, '.'))
        {
            return isset($this->_datas[$var]) ? $this->_datas[$var] : null;
        }

        $arrays = explode('.', $var);
        
        $datas = $this->_datas;

        foreach($arrays as $arr)
        {
            if(!is_array($datas) || !isset($datas[$arr]))
                return null;
            $datas = $datas[$arr];
        }

        return $datas;
    }

    /**
     * Setter
     * It will only set value if the var has not already been defined
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var name
     * @param mixed $value the value to assign to $var
     * @return mixed the value if succeed of false
     */
    public function set($var, $value)
    {
        $var = (string)$var;

        if(false === strpos($var, '.'))
        {
            return (isset($this->_datas[$var]) ? false : ($this->_datas[$var] = $value));
        }

        $var = explode('.', $var);

        $datas =& $this->_datas;
        
        foreach($var as $arr)
        {
            if(!is_array($datas) || !isset($datas[(string)$arr])) return false;
            $datas =& $datas[$arr];
        }

        $datas = $value;

        return $value;
    }
}
