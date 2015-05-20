<?php
/**
 * Plugins class
 * This class is used to manage plugins
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
 * This object represents the user running the script
 * @uses User the user object
 * @package OWR
 */
abstract class Plugins
{
    /**
     * @var array list of plugins, name => object
     * @access private
     */
    static private $__plugins = array();

    /**
     * Inits all user's plugins 
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    static public function init()
    {
        $plugins = User::iGet()->getConfig('plugins');
        if(empty($plugins)) return;
        
        foreach(new \DirectoryIterator(OWR_PLUGINS_PATH) as $plugin)
        {
            if($plugin->isDot() || !$plugin->isDir()) continue;

            $dirName = $plugin->getBaseName();
            
            if(!isset($plugins[$dirName])) continue;

            if(!@include($plugin->getPathName()  . DIRECTORY_SEPARATOR . $dirName . '.php'))
                continue;

            self::$__plugins[$dirName] = new $dirName;
        }
    }

    /**
     * Triggers plugin's method
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    static public function trigger()
    {
        list(,$name) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $name = strtolower(str_replace(array(__NAMESPACE__ . '\\', '\\', DIRECTORY_SEPARATOR), array('', '_', '_'), $name['class'] . '_' . $name['function']));

        foreach(self::$__plugins as $plugin)
        {
            if(method_exists($plugin, $name))
                call_user_func_array(array($plugin, $name), func_get_args());
        }
    }

    /**
     * Triggers plugin's 'pre' method
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    static public function pretrigger()
    {
        list(,$name) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $name = 'pre_' . strtolower(str_replace(array(__NAMESPACE__ . '\\', '\\', DIRECTORY_SEPARATOR), array('', '_', '_'), $name['class'] . '_' . $name['function']));

        foreach(self::$__plugins as $plugin)
        {
            if(method_exists($plugin, $name))
                call_user_func_array(array($plugin, $name), func_get_args());
        }
    }

    /**
     * Triggers plugin's 'post' method
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    static public function posttrigger()
    {
        list(,$name) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $name = 'post_' . strtolower(str_replace(array(__NAMESPACE__ . '\\', '\\', DIRECTORY_SEPARATOR), array('', '_', '_'), $name['class'] . '_' . $name['function']));

        foreach(self::$__plugins as $plugin)
        {
            if(method_exists($plugin, $name))
                call_user_func_array(array($plugin, $name), func_get_args());
        }
    }

    /**
     * Returns the list of all available plugins
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return array list of available plugins
     */
    static public function getList()
    {
        $plugins = array();
        $userPlugins = User::iGet()->getConfig('plugins');
 
        foreach(new \DirectoryIterator(OWR_PLUGINS_PATH) as $plugin)
        {
            if($plugin->isDot() || !$plugin->isDir()) continue;
            $name = $plugin->getBaseName();
            $plugins[$name] = isset($userPlugins[$name]);
        }

        return $plugins;
    }

    /**
     * Executes a specific method of a plugin
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param OWR\Request the request
     */
    static public function execute(Request $request)
    {
        $name = explode('_', $request->do); // call "plugins_method"
        if(2 !== count($name) || !isset($this->__plugins[$name[0]]) || !method_exists($name[0], $name[1]))
            throw new Exception(sprintf(Utilities::iGet()->_('Invalid action "%s"'), $request->do), Exception::E_OWR_BAD_REQUEST);

        return $name[0]->{$name[1]}($request);
    }    
}
