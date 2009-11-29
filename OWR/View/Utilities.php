<?php
/**
 * Renderer class
 * This class uses Dwoo (http://dwoo.org) to render page
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
 * @subpackage View
 */
namespace OWR\View;
use OWR\Singleton as Singleton,
    OWR\Cache as Cache,
    OWR\Config as Config,
    OWR\User as User;
/**
 * This object is used to render page with Dwoo
 * @uses Singleton implements the singleton pattern
 * @uses Cache check dwoo cache directories
 * @uses User the current user
 * @uses OWR\Config get the cachetime
 * @package OWR
 * @subpackage Renderer
 */
class Utilities extends Singleton
{
    /**
     * @var array cache of gettext
     * @access protected
     */
    protected $_translations;

    /**
     * @var boolean true if we have to write the cache translations file
     * @access protected
     */
    protected $_transChanged = false;

    /**
     * Constructor
     * Tries to get cached responses from gettext
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    protected function __construct()
    {
        $this->_translations = Cache::get('translations_'.User::iGet()->getLang(), 
                                                    Config::iGet()->get('cacheTime')) ?:
                                array();
    }

    /**
     * Destructor
     * Will write translations cache if it has changed
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __destruct()
    {
        if($this->_transChanged && !empty($this->_translations))
            Cache::write('translations_'.User::iGet()->getLang(), $this->_translations);
    }

    /**
     * Returns a preformatted list
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $values the datas
     * @param string $ULclass the css class for &lt;ul&gt;, optionnal
     * @param string $LIclass the css class for &lt;li&gt;, optionnal
     * @return string the list
     */
    public function makeList(array $values, $ULclass = '', $LIclass = '')
    {
        $ULclass = (string) $ULclass;
        $LIclass = (string) $LIclass;

        $list = '<ul'.(!empty($ULclass) ? ' class="'.$ULclass.'"' : '').'>';
    
        foreach($values as $key => $value)
        {
            if(is_array($value) && !empty($value['contents']))
            {
                $list .= '<li'.(!empty($LIclass) ? ' class="'.$LIclass.'"' : '').'><em>'.htmlentities($key, ENT_COMPAT, 'UTF-8', false).'</em>';
                if(!empty($value['attributes']))
                {
                    $list .= ' (';
                    foreach($value['attributes'] as $k=>$v)
                    {
                        $list .= htmlentities($key, ENT_COMPAT, 'UTF-8').'='.htmlentities(is_array($v) ? join(',', $v) : $v, ENT_COMPAT, 'UTF-8', false).';';
                    }
                    $list .= ')';
                }
                $list .= ' : ';
                if(!empty($value['contents']))
                {
                    if(is_array($value['contents']))
                    {
                        $list .= '<li'.(!empty($LIclass) ? ' class="'.$LIclass.'"' : '').'>';
                        $list .= $this->makeList($value['contents'], $ULclass, $LIclass);
                        $list .= '</li>';
                    }
                    else
                    {
                        $list .= htmlentities($value['contents'], ENT_COMPAT, 'UTF-8', false);
                    }
                }
                $list .= '</li>';
            }
            elseif(is_array($value))
            {
                $list .= '<li'.(!empty($LIclass) ? ' class="'.$LIclass.'"' : '').'><em>'.htmlentities($key, ENT_COMPAT, 'UTF-8', false).'</em> : ';
                $list .= $this->makeList($value, $ULclass, $LIclass);
                $list .= '</li>';
            }
            elseif(!empty($value))
            {
                $list .= '<li'.(!empty($LIclass) ? ' class="'.$LIclass.'"' : '').'><em>'.htmlentities($key, ENT_COMPAT, 'UTF-8', false).'</em> : '.htmlentities($value, ENT_COMPAT, 'UTF-8', false).'</li>';
            }
        }
    
        $list .= '</ul>';
    
        return $list;
    }

    /**
     * Used in templates to cache gettext() response 
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $name The message being translated
     * @return string a translated string if one is found in the translation table, else the submitted message
     */
    public function _($name)
    {
        $name = (string) $name;
        if(!isset($this->_translations[$name]))
        {
            $this->_translations[$name] = gettext($name);
            $this->_transChanged = true;
        }

        return $this->_translations[$name];
    }
}