<?php
/**
 * DAO Object representing the table news
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
use OWR\DAO as DAO, OWR\DB\Request as DBRequest;
/**
 * This object represents the table news
 * @uses DAO extends the base class
 * @uses DBRequest defines the fields type
 * @package OWR
 * @subpackage DAO
 */
class News extends DAO
{
    /**
     * @var int new's id
     * @access public
     */
    public $id;

    /**
     * @var int stream's id
     * @access public
     */
    public $rssid;

    /**
     * @var int last update timestamp
     * @access public
     */
    public $lastupd;

    /**
     * @var string new's title
     * @access public
     */
    public $title;

    /**
     * @var string new's link
     * @access public
     */
    public $link;

    /**
     * @var int new's publication date
     * @access public
     */
    public $pubDate;

    /**
     * @var string new's url md5
     * @access public
     */
    public $hash;

    /**
     * @var string new's author
     * @access public
     */
    public $author;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __construct()
    {
        parent::__construct();
        $this->_idField = 'id';
        $this->_uniqueFields = array('hash' => true);
        $this->_fields = array(
            'id'        =>  array('required' => true,     'type' => \PDO::PARAM_INT),
            'rssid'     =>  array('required' => true,     'type' => \PDO::PARAM_INT),
            'title'     =>  array('required' => true,     'type' => \PDO::PARAM_STR), 
            'link'      =>  array('required' => true,     'type' => DBRequest::PARAM_URL),
            'lastupd'   =>  array('required' => false,    'type' => DBRequest::PARAM_CURRENT_TIMESTAMP),
            'pubDate'   =>  array('required' => false,    'type' => DBRequest::PARAM_CURRENT_TIMESTAMP),
            'hash'      =>  array('required' => true,     'type' => DBRequest::PARAM_HASH),
            'author'    =>  array('required' => false,    'type' => \PDO::PARAM_STR)
        );
        $this->_userRelations = array(
            'news_relations'    => array('id'                   => 'newsid'),
            'streams_relations' => array('news_relations.rssid' => 'rssid')
        );
        $this->_relations = array(
            'news_relations'    => array('id'   => 'newsid'),
            'news_contents'     => array('id'   => 'id'),
            'streams_relations' => array('news_relations.rssid' => 'rssid')
        );
    }
}