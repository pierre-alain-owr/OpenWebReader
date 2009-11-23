<?php
/**
 * Config var container class
 * Read-only access for predefined vars (from config.php config file)
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
 * This object stores the configuration values in read access only
 * @uses PrivateSingleton implements the singleton pattern in private mode
 * @package OWR
 */
class Config extends PrivateSingleton
{
    /**
     * Constructor
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $datas the config values
     */
    protected function __construct(array $datas)
    {
        if(empty($datas)) die('please include config file');
        parent::__construct($datas);
    }

    /**
     * Executed when deserializing this object
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __wakeUp()
    {
        parent::__wakeUp();
    }

    /**
     * Returns the url following the directive uriStyle
     *
     * @access public
     * @param string $do the action
     * @param string $params additional parameters
     * @param boolean $escape must-we use '&nbsp;' instead of '&' ?
     * @return string the url
     */
    public function makeURI($do, $params='', $escape = true)
    {
        $do = (string) $do;
        $params = (string) $params;
        $url = $this->_datas['surl'];
        switch($this->_datas['uriStyle'])
        {
            case 'action':
                if(!empty($params))
                {
                    $params = false !== strpos($do, '?') ? ($escape ? '&amp;' : '&').$params : '?'.$params;
                    $url .= $do.$params;
                }
                else $url .= $do;
                break;

            case 'index':
            default:
                $url .= 'index.php';
                if(!empty($do))
                {
                    $url .= '?do='.$do;
                    if(!empty($params))
                    {
                        $url .= ($escape ? '&amp;' : '&').$params;
                    }
                }
                elseif(!empty($params))
                {
                    $url .= '?'.$params;
                }
                break;
        }

        return $url;
    }

    /**
     * Returns the current controller
     * This function returns an instance of OWR\CLI\Controller if we are in CLI mode
     * Else OWR\Rest\Controller if we are in rest api
     * Else returns OWR\Controller
     *
     * @access public
     * @return mixed the Controller instance
     */
    public function getController()
    {
        return CLI ? namespace\CLI\Controller::iGet() : (
                REST ? namespace\REST\Controller::iGet() :
                    Controller::iGet()
            );
    }
}