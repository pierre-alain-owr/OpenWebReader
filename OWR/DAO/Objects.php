<?php
/**
 * DAO Object representing the table objects
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
 * @subpackage DAO
 */
namespace OWR\DAO;
use OWR\DAO,
    OWR\DB\Request as DBRequest,
    OWR\Exception;
/**
 * This object represents the table objects
 * @uses DAO extends the base class
 * @uses DBRequest defines the fields type
 * @package OWR
 * @subpackage DAO
 */
class Objects extends DAO
{
    /**
     * @var int object's id
     * @access public
     */
    public $id;

    /**
     * @var string object's type
     * @access public
     */
    public $type;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __construct()
    {
        $this->_idField = 'id';
        $this->_fields = array(
            'id'    =>  array('required' => false, 'type' => DBRequest::PARAM_NULL, 'default' => null),
            'type'  =>  array('required' => true, 'type' => \PDO::PARAM_STR)
        );
        parent::__construct();
    }

    /**
     * Gets a unique ID
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $type the type of the objects to insert
     * @return int
     */
    public function getUniqueId($type)
    {
        $type = (string) $type;

        switch($type)
        {
            case 'news':
            case 'streams':
            case 'users':
            case 'streams_groups':
            case 'news_tags':
                break;

            default:
                throw new Exception('Invalid type', Exception::E_OWR_BAD_REQUEST);
                break;
        }

        return (int) parent::$_db->setP('
    INSERT INTO objects
        SET id=?, type=?', new DBRequest(array('type'=>$type), array('id' => $this->_fields['id'], 'type' => $this->_fields['type'])), 'exec', true, true);
    }
}