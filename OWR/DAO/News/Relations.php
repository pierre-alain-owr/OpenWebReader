<?php
/**
 * DAO Object representing the table news_relations
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
 * @subpackage DAO\news
 */
namespace OWR\DAO\news;
use OWR\DAO,
    OWR\DB\Request as DBRequest;
/**
 * This object represents the table news_relations
 * @uses DAO extends the base class
 * @uses DBRequest defines the fields type
 * @package OWR
 * @subpackage DAO\news
 */
class Relations extends DAO
{
    /**
     * @var int new's id
     * @access public
     */
    public $newsid;

    /**
     * @var int user's id
     * @access public
     */
    public $uid;

    /**
     * @var int new' status
     * @access public
     */
    public $status;

    /**
     * @var int stream's id
     * @access public
     */
    public $rssid;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __construct()
    {
        $this->_fields = array(
            'newsid'                    => array('required' => true, 'type' => \PDO::PARAM_INT),
            'rssid'                     => array('required' => true, 'type' => \PDO::PARAM_INT),
            'status'                    => array('required' => false, 'type' => \PDO::PARAM_INT, 'default' => 1),
            'uid'                       => array('required' => true, 'type' => \PDO::PARAM_INT)
        );
        $this->_userRelations = array(
            'streams_relations'         => array('rssid'    => 'rssid'),
            'news_relations_tags'       => array('newsid'   => 'newsid'),
            'streams_relations_name'    => array('rssid'    => 'rssid')
        );
        $this->_relations = array(
            'news'                      => array('newsid'   => 'id'),
            'news_contents'             => array('newsid'   => 'id'),
            'streams'                   => array('rssid'    => 'id'),
            'streams_contents'          => array('rssid'    => 'rssid')
        );
        $this->_weight = 11;
        parent::__construct();
    }
}