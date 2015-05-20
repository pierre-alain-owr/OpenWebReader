<?php
/**
 * Abstract class for singleton pattern
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
use OWR\Interfaces\Singleton as iSingleton;
/**
 * This object is used to implement the singleton pattern
 * @abstract
 * @package OWR
 */
abstract class Singleton implements iSingleton
{
    /**
    * @var array store instancied objects
    * @access private
    * @static
    */
    private static $_instances = array();

    /**
     * Constructor
     *
     * @access protected
     */
    protected function __construct() {}

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
     * @param mixed $instance the instance to register
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
}
