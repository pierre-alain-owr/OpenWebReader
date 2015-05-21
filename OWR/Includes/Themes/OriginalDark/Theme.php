<?php
/**
 * Themes class
 * This class is used to manage theme
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
namespace OWR\Includes\Themes\OriginalDark;
use OWR\Theme as pTheme, OWR\User, OWR\Config, OWR\Dates, OWR\Plugins,OWR\Includes\Themes\Original\Theme as Original;

/**
 * Default theme
 *
 * @uses OWR\View the page renderer
 * @uses OWR\User the current user
 * @uses OWR\Config the config instance
 * @uses OWR\Plugins the plugins object
 * @package OWR
 */
class Theme extends Original
{
    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        $this->_parent = 'Original';
        parent::__construct();
    }
}
