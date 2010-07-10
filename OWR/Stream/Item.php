<?php
/**
 * StreamItem class
 * This class represents an item from a stream
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
 * @subpackage Stream
 */
namespace OWR\Stream;
/**
 * This object is used to deal with an item from a stream
 * @package OWR
 * @subpackage Stream
 */
class Item
{
    /**
    * @var array the item
    * @access private
    */
    private $_item;

    /**
    * @var string version of stream (rss, rdf, atom)
    * @access private
    */
    private $_version;

    /**
     * Constructor
     * We try to harmonize the datas between the different accepted formats
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $stream the stream to represent
     * @param string $version the version of the stream (atom, rdf, rss..)
     * @param mixed $reader the stream object
     */
    public function __construct(array $stream, $version, Reader $reader)
    {
        $this->_item = $stream;
        $this->_version = $version;
        if(isset($this->_item['title']['contents']))
            $this->_item['title']['contents'] = (string) strip_tags($this->_item['title']['contents']);
        elseif(!isset($this->_item['description']['contents']))
        {
            $this->_item['title']['contents'] = 'No title';
        }
        else $this->_item['title']['contents'] = (string) mb_substr(strip_tags($this->_item['description']['contents']), 0, 255, 'UTF-8');

        if('atom' === $this->_version)
        { // atom
            if(isset($this->_item['updated']['contents']))
            {
                $this->_item['pubDate']['contents'] = strtotime((string) $this->_item['updated']['contents']);
            }
            elseif(isset($this->_item['published']['contents']))
            {
                $this->_item['pubDate']['contents'] = strtotime((string) $this->_item['published']['contents']);
            }
            else $item['pubDate']['contents'] = time();

            if(count($this->_item['link']) > 1)
            {
                $ok = false;
                foreach($this->_item['link'] as $link)
                {
                    foreach($link['attributes'] as $type=>$val)
                    {
                        if('rel' === $type && 'alternate' === $val && !empty($link['attributes']['href']))
                        {
                            $ok = true;
                            break;
                        }
                    }

                    if($ok)
                    {
                        $this->_item['url']['contents'] = html_entity_decode((string) $link['attributes']['href'], ENT_COMPAT, 'UTF-8');
                        break;
                    }
                }
            }
            elseif(isset($this->_item['link'][0]))
            {
                $this->_item['url']['contents'] = $this->_item['link'][0]['attributes']['href'];
            }

            if((!empty($this->_item['content']) && $node =& $this->_item['content']) || (!empty($this->_item['summary']) && $node =& $this->_item['summary']))
            {
                if(count($node) > 2)
                {
                    foreach($node as $k=>$content)
                    {
                        if(isset($content['attributes']) && isset($content['attributes']['type']) && ('xhtml' === $content['attributes']['type'] || 'html' === $content['attributes']['type']) && !empty($content['contents']))
                        {
                            $this->_item['description']['contents'] = $content['contents'];
                            unset($node[$k]);
                            break;
                        }
                    }
                }
                elseif(isset($node['contents']))
                {
                    $this->_item['description']['contents'] = $node['contents'];
                }
            }
            unset($node);

            if(!empty($this->_item['author']))
            {
                $author = '';
                if(isset($this->_item['author']['name']) && isset($this->_item['author']['name']['contents']))
                {
                    $author .= $this->_item['author']['name']['contents'];
                }

                if(isset($this->_item['author']['email']) && isset($this->_item['author']['email']))
                {
                    $author .= empty($author) ? $this->_item['author']['email']['contents'] : ', '.$this->_item['author']['email']['contents'];
                }

                if(isset($this->_item['author']['uri']) && isset($this->_item['author']['uri']))
                {
                    $author .= empty($author) ? $this->_item['author']['uri']['contents'] : ', '.$this->_item['author']['uri']['contents'];
                }

                if(!empty($author))
                    $this->_item['author']['contents'] = $author;
            }
            else
            {
                $this->_item['author']['contents'] = $reader->get('author');
            }
        }
        else
        { // rss
            $this->_item['pubDate']['contents'] = !empty($this->_item['pubDate']['contents']) ? strtotime((string) $this->_item['pubDate']['contents']) : time();
            $this->_item['url']['contents'] = html_entity_decode((string)$this->_item['link']['contents'], ENT_COMPAT, 'UTF-8');
        }

        if(empty($this->_item['author']['contents']) && !empty($this->_item['contributor']['contents']))
            $this->_item['author']['contents'] = $this->_item['contributor']['contents'];
    }

    /**
     * Getter
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $var the var
     * @return mixed the value on success or false
     */
    public function __get($var)
    {
        return $this->get($var);
    }

    /**
     * Getter
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $type what do we want ?
     * @return mixed the value on success or false
     */
    public function get($type = '')
    {
        $type = (string) $type;

        switch($type)
        {
            case 'link':
                return ((string)!empty($this->_item['url']['contents']) ? $this->_item['url']['contents'] : false);
                break;
            case 'title':
                return ((string)!empty($this->_item['title']['contents']) ? $this->_item['title']['contents'] : false);
                break;
            case 'pubDate':
                return ((string)!empty($this->_item['pubDate']['contents']) ? $this->_item['pubDate']['contents'] : false);
                break;
            case 'author':
                if(!empty($this->_item['author']['contents'])) return (string)$this->_item['author']['contents'];
                return (string)(!empty($this->_item['creator']['contents']) ? $this->_item['creator']['contents'] : false);
                break;
            case 'description':
                if(!empty($this->_item['encoded']['contents']))
                { // encoded has the preference :-)
                    return (string)$this->_item['encoded']['contents'];
                }
                elseif(!empty($this->_item['description']['contents']))
                {
                    return (string)$this->_item['description']['contents'];
                }
                elseif(!empty($this->_item['summary']['contents']))
                {
                    return (string)$this->_item['summary']['contents'];
                }
                else return false;
                break;
            default:
                return $this->_item;
                break;
        }
    }
}