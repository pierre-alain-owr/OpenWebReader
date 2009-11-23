<?php
/**
 * Object used to filter inputs
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
 * This object is the default input filter
 * @uses Exception the exceptions handler
 * @uses Singleton implements the singleton pattern
 * @uses Cache check that the directory htmlpurifier exists
 * @package OWR
 */
class Filter extends Singleton
{
    /**
    * @var mixed the HTMLPurifier instance
    * @access private
    */
    private $_filter;

    /**
     * Constructor
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    protected function __construct()
    {
        class_exists('\HTMLPurifier', false) || include HOME_PATH.'libs/HTMLPurifier/HTMLPurifier.standalone.php';
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.TidyLevel', 'heavy' );
        $config->set('Cache.SerializerPath', HOME_PATH.'cache');
        $config->set('HTML.Doctype', 'XHTML 1.0 Strict');
        $config->set('HTML.ForbiddenAttributes', '*@style');
        $config->set('HTML.SafeObject', true);
        $config->set('HTML.SafeEmbed', true);
        $this->_filter = new \HTMLPurifier($config);
    }

    /**
     * Purify the given value
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $str the string to purify
     * @return string the purified string
     */
    public function purify($str)
    {
        return $this->_filter->purify((string) $str);
    }
}