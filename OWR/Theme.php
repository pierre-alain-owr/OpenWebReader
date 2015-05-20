<?php
/**
 * Abstract class managing themes
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
use OWR\Interfaces\Theme as iTheme;
/**
 * Abstract class used to manage themes
 * All themes MUST extends this class
 *
 * @uses View the page renderer
 * @uses Exception the exception handler
 * @uses User the current user
 * @package OWR
 */
abstract class Theme implements iTheme
{
    /**
     * @var string Default theme name
     * @access protected
     * @static
     */
    static protected $_defaultName = 'Original';

    /**
     * @var object instance of current theme object
     * @access private
     * @static
     */
    static private $__theme;

    /**
     * @var string default theme class name
     * @access protected
     */
    protected $_defaultClassName;

    /**
     * @var string current theme class name
     * @access protected
     */
    protected $_className;

    /**
     * @var string current theme name (uses default if not specified)
     * @access protected
     */
    protected $_name;

    /**
     * @var string path to current theme
     * @access protected
     */
    protected $_path;

    /**
     * @var string path to current theme pages templates
     * @access protected
     */
    protected $_pagesPath;

    /**
     * @var string path to current theme blocks templates
     * @access protected
     */
    protected $_blocksPath;

    /**
     * @var object instance of OWR\View
     * @access protected
     */
    protected $_view;

    /**
     * @var array list of theme pages
     * @access protected
     */
    protected $_pages = array('index' => true, 'opml' => true, 'opensearch' => true, 'upload' => true, 'rss' => true);

    /**
     * @var string name of parent theme
     * @access protected
     */
    protected $_parent;

    /**
     * @var string name of parent class name
     * @access protected
     */
    protected $_parentClassName;

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        $this->_view = View::iGet();

        $this->_defaultClassName = 'OWR\Includes\Themes\\' . self::$_defaultName . '\Theme';

        $this->_name = ucfirst((string) (User::iGet()->getConfig('theme') ?: self::$_defaultName));

        $this->_className = 'OWR\Includes\Themes\\' . $this->_name . '\Theme';

        $this->_path = OWR_THEMES_PATH . $this->_name . DIRECTORY_SEPARATOR;
        $this->_pagesPath = $this->_path . 'tpl' . DIRECTORY_SEPARATOR;
        $this->_blocksPath = $this->_path . 'tpl' . DIRECTORY_SEPARATOR . 'blocks' . DIRECTORY_SEPARATOR;

        if(!is_subclass_of($this->_className, __CLASS__))
            throw new Exception('Your class theme needs to extend class "' . __CLASS__ . '"');
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
        if(!isset(self::$__theme))
        {
            $theme = 'OWR\Includes\Themes\\' . ucfirst((string) (User::iGet()->getConfig('theme') ?: self::$_defaultName)) . '\Theme';
            self::$__theme = new $theme;
        }

        return self::$__theme;
    }


    /**
     * Returns list of theme pages
     *
     * @access public
     * @return $this->_pages
     */
    final public function getPages()
    {
        return (array) $this->_pages;
    }

    /**
     * Returns path to : template if $tpl is specified (pages or blocks), or theme
     *
     * @param string $tpl optionnal, name of the template to get path
     * @access public
     * @return string path
     */
    final public function getPath($tpl = null)
    {
        return isset($tpl) ? (isset($this->_pages[(string) $tpl]) ?
            $this->_pagesPath : $this->_blocksPath) : $this->_path;
    }

    /**
     * Wrapper for all theme methods
     * It will call method in this order : theme, parent theme, default theme
     *
     * @param string $name method to call
     * @param array $args arguments to pass to the method
     * @access public
     * @static
     * @return mixed return from method call
     */
    static public function __callStatic($name, $args)
    {
        if(method_exists(self::$__theme, $name))
            $call = self::$__theme;
        elseif(isset($this->_parentClassName) && method_exists($this->_parentClassName, $name))
            $call = $this->getParentTheme();
        elseif(method_exists($this, $name))
            $call = $this;
        else
            throw new Exception('Invalid call to missing method "' . get_class(self::$__theme) . '::' . $name . '"');

        return call_user_func_array(array($call, $name), $args);
    }

    /**
     * Wrapper for all theme methods
     * It will call method in this order : theme, parent theme, default theme
     *
     * @param string $name method to call
     * @param array $args arguments to pass to the method
     * @access public
     * @static
     * @return mixed return from method call
     */
    public function __call($name, $args)
    {
        if(method_exists(self::$__theme, $name))
            $call = self::$__theme;
        elseif(isset($this->_parentClassName) && method_exists($this->_parentClassName, $name))
            $call = $this->_parentClassName;
        elseif(method_exists(__CLASS__, $name))
            $call = __CLASS__;
        else
            throw new Exception('Invalid call to missing method "' . get_class(self::$__theme) . '::' . $name . '"');

        return call_user_func_array(array($call, $name), $args);
    }
    
    /**
     * Returns parent theme instance if exists
     *
     * @access public
     * @return mixed $this->_parent instance or false if empty
     */
    final public function getParentTheme()
    {
        if(!isset($this->_parent)) return false;
        
        $theme = 'OWR\Includes\Themes\\' . ucfirst((string) $this->_parent) . '\Theme';

        return new $theme;
    }

    /**
     * Returns parent theme name
     *
     * @access public
     * @return string $this->_parent
     */
    final public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Returns the list of all available themes
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return array list of available themes
     */
    static public function getList()
    {
        $themes = array();

        $userTheme = User::iGet()->getConfig('theme');
        
        foreach(new \DirectoryIterator(OWR_THEMES_PATH) as $theme)
        {
            if($theme->isDot() || !$theme->isDir()) continue;
            
            $name = $theme->getBaseName();
            $themes[$name] = $userTheme === $name;
        }

        return $themes;
    }

    /**
     * Removes all indentation from file
     *
     * @access public
     * @param string $file the file to minify
     * @return string the mimnified file
     */
    public function minify($file)
    {
        $content = @file_get_contents($file);
        if(empty($content)) return '';

//        $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
        $content = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $content);

        return $content;
    }
}
