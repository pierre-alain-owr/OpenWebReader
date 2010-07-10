<?php
/**
 * DAO Object representing the table streams
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
    OWR\Config;
/**
 * This object represents the table streams
 * @uses DAO extends the base class
 * @uses DBRequest defines the fields type
 * @uses Exception the exception handler
 * @package OWR
 * @subpackage DAO
 */
class Streams extends DAO
{
    /**
     * @var int stream's id
     * @access public
     */
    public $id;

    /**
     * @var string stream's url
     * @access public
     */
    public $url;

    /**
     * @var int stream's time to leave
     * @access public
     */
    public $ttl;

    /**
     * @var int stream's last update timestamp
     * @access public
     */
    public $lastupd;

    /**
     * @var string stream's favicon
     * @access public
     */
    public $favicon;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __construct()
    {
        $this->_idField = 'id';
        $this->_uniqueFields = array('hash' => true);
        $this->_fields = array(
            'url'       =>  array('required' => true,  'type' => DBRequest::PARAM_URL),
            'ttl'       =>  array('required' => true,  'type' => \PDO::PARAM_INT, 'default' => Config::iGet()->get('defaultStreamRefreshTime')),
            'id'        =>  array('required' => true,  'type' => \PDO::PARAM_INT),
            'lastupd'   =>  array('required' => false, 'type' => DBRequest::PARAM_CURRENT_TIMESTAMP),
            'favicon'   =>  array('required' => false, 'type' => \PDO::PARAM_STR),
            'hash'      =>  array('required' => true,  'type' => DBRequest::PARAM_HASH),
            'status'    =>  array('required' => false, 'type' => DBRequest::PARAM_CURRENT_TIMESTAMP, 'default' => 0)
        );
        $this->_userRelations = array(
            'streams_relations'         => array('id' => 'rssid'),
            'streams_relations_name'    => array('id' => 'rssid')
        );
        $this->_relations = array(
            'streams_contents'          => array('id' => 'rssid')
        );
        parent::__construct();
    }

    public function declareUnavailable()
    {
        if(empty($this->{$this->_idField}))
        {
            throw new Exception('Please specify the stream to set as unavailable', Exception::E_OWR_BAD_REQUEST);
        }

        $this->status = time();
        $this->save();
    }
}