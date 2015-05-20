<?php
/**
 * Cache class
 * Class for cache tools utility
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
 * This object manages cache files
 * @package OWR
 */
class Cache
{
    /**
     * @var string name of cache object class
     * @access private
     */
    static private $__instance;

    /**
     * Constructor
     *
     * @access private
     */
    private function __construct() {}

    /**
     * Init cache object string
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @static
     */
    static protected function _init()
    {
        if(isset(self::$__instance)) return true;

        self::$__instance = 'memcache' === Config::iGet()->get('cacheType') ? 'OWR\Cache\Memcache' : 'OWR\Cache\CFile';
    }

    /**
     * Wrapper for cache object methods
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $name the name of the method to call
     * @param array $args the arguments to pass to the method
     * @static
     */
    static public function __callStatic($name, $args)
    {
        self::_init();

        if(!method_exists(self::$__instance, $name))
            throw new Exception(sprintf(Utilities::iGet()->_('Invalid action "%s"'), self::$__instance . '::' . $name), Exception::E_OWR_DIE);

        return call_user_func_array(array(self::$__instance, $name), $args);
    }
}
