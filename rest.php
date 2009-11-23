<?php
/**
 * Handling REST requests
 * Get the request and call the controller
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
 * @licence http://www.gnu.org/copyleft/gpl.html
 * @package OWR
 */
namespace OWR\REST;

define('PATH', __DIR__.DIRECTORY_SEPARATOR); // define root path
define('HOME_PATH', PATH.'OWR'.DIRECTORY_SEPARATOR); // define home path

if(!file_exists(HOME_PATH.'cfg.php'))
{ // not installed
    exit;
}

// include the config file
require HOME_PATH.'cfg.php';

use OWR\Exception as Exception,
    OWR\Logs as Logs;
try
{
    Controller::iGet()->execute(new Request)->renderPage();
}
catch(Exception $e)
{
    Logs::iGet()->log($e->getContent(), $e->getCode());
    Controller::iGet()->renderPage($e->getCode());
}

exit;
