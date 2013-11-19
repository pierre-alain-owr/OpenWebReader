<?php
/**
 * Class used to manipulate dates
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
 * Class used to manipulate dates
 * @uses \IntlDateFormatter
 * @package OWR
 */
class Dates
{
    /**
     * @var array instances of \IntlDateFormatter
     * @static
     * @access protected
     */
    static protected $_instances = array();

    /**
     * Converts timestamp to string date by locale
     *
     * @param int $timestamp timestamp to convert to string
     * @param string $locale optionnal locale to convert the timestamp to
     * @access public
     * @static
     * @return formatted date
     */
    static public function format($timestamp, $locale = null)
    {
        if(!isset($locale))
            $locale = User::iGet()->getLang();

        if(!isset(self::$_instances[$locale]))
            self::$_instances[$locale] = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::MEDIUM);

        return self::$_instances[$locale]->format((int) $timestamp);
    }
}