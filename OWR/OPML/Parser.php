<?php
/**
 * OPML parser class
 * This class extends XMLReader and is used to parse OPML
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
 * @subpackage OPML
 */
namespace OWR\OPML;
use \XMLReader,
    OWR\Exception,
    OWR\Strings,
    OWR\cURLWrapper,
    OWR\View\Utilities;
/**
 * This object is used to parse OPML
 * @uses Exception the exceptions handler
 * @uses Strings xml entities and M$ bad chars conversion
 * @uses cURLWrapper get the remote opml file
 * @uses OWR\View\Utilities translate errors
 * @package OWR
 * @subpackage OPML
 */
class Parser extends XMLReader
{
    /**
    * @var array list of streams in parsed OPML
    * @access protected
    */
    protected $_streams = array();

    /**
    * @var array OPML structure
    * @access protected
    */
    protected $_trees = array(
        'title'             => array('required' => FALSE),
        'dateCreated'       => array('required' => FALSE),
        'dateModified'      => array('required' => FALSE),
        'ownerName'         => array('required' => FALSE),
        'ownerEmail'        => array('required' => FALSE),
        'ownerId'           => array('required' => FALSE),

        'outline'      => array(
            'type'          => array('required' => TRUE),
            'text'          => array('required' => TRUE),
            'xmlUrl'        => array('required' => TRUE),
            'htmlUrl'       => array('required' => FALSE),
            'language'      => array('required' => FALSE),
            'title'         => array('required' => FALSE),
            'version'       => array('required' => FALSE),
            'description'   => array('required' => FALSE),
            'created'       => array('required' => FALSE),
            'category'      => array('required' => FALSE),
        )
    );

    /**
    * @var int number of streams in OPML
    * @access protected
    */
    protected $_itemDepth;

    /**
    * @var string the name of the current folder if any
    * @access protected
    */
    protected $_folder;

    /**
    * @var string the name of the current node
    * @access protected
    */
    protected $_localName;

    /**
    * @var string the name of the parent node
    * @access protected
    */
    protected $_parentLocalName;

    /**
     * Launches the parse of the opml file found at $uri
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $opml the uri of the OPML to parse
     * @param boolean $isUploaded is the uri a path to an uploaded file ?
     * @return boolean true on success
     */
    public function parse($opml, $isUploaded = false)
    {
        $this->_itemDepth = 0;

        libxml_use_internal_errors(true);

        if(isset($this->_streams[$opml])) return true;

        if(!$isUploaded)
        {
            $src = cURLWrapper::get($opml, array(), true, true);

            if(!$src || !($src = Strings::toXML($src, false, false)) || !@$this->XML($src, 'UTF-8', LIBXML_NOBLANKS | LIBXML_NOCDATA))
            {
                throw new Exception(sprintf(Utilities::iGet()->_('Can not parse stream "%"'), $opml), Exception::E_OWR_WARNING);
                return false;
            }
        }
        else
        {
            if(!@$this->open($opml, 'UTF-8', LIBXML_NOBLANKS | LIBXML_NOCDATA))
            {
                @unlink($opml);
                throw new Exception('Can not open temporary OPML file', Exception::E_OWR_WARNING);
                return false;
            }
        }

        $this->_currentStream = $opml;

        $this->_streams[$opml] = array();

        $this->_parentLocalName = $this->_localName = $this->_folder = null;

        while(@$this->read())
        {
            if(self::ELEMENT === $this->nodeType)
            {
                if('opml' === $this->localName || 'head' === $this->localName || 'body' === $this->localName)
                    continue;

                if('outline' === $this->localName)
                {
                    $type = $this->getAttribute('type');
                    if('rss' === $type || 'pie' === $type) // atom === 'pie'
                    {
                        $this->_nodeTree[$this->depth] = $this->_localName = $this->localName;
                        $this->_itemDepth++;

                        if($this->hasAttributes)
                        {
                            if(!$this->_parseAttributes())
                            {
                                unset($this->_streams[$this->_currentStream]['item'][$this->_itemDepth]);
                                $this->_itemDepth--;
                                continue;
                            }
                        }
                    }
                    elseif('folder' === $type || !$this->isEmptyElement)
                    {
                        $this->_nodeTree[$this->depth] = $this->_localName = 'folder';
                        $title = $this->getAttribute('title');
                        if(!$title)
                            $title = $this->getAttribute('text');

                        if(!$title)
                        {
                            throw new Exception('Missing title for folder', Exception::E_OWR_WARNING);
                            $this->_folder = null;
                        }
                        else $this->_folder = $title;
                    }
                }
                else
                {
                    $this->_nodeTree[$this->depth] = $this->_localName = $this->localName;

                    if($this->hasAttributes)
                    {
                        $this->_parseAttributes();
                    }

                    if($this->isEmptyElement)
                    {
                        $this->_parentLocalName = isset($this->_nodeTree[$this->depth - 1]) ? $this->_nodeTree[$this->depth - 1] : null;
                        continue;
                    }
                }
            }
            elseif(self::TEXT === $this->nodeType || self::CDATA === $this->nodeType)
            {
                if($this->hasValue)
                {
                    $this->_parseNode();
                }
            }
            elseif(self::END_ELEMENT === $this->nodeType)
            {
                if('opml' === $this->localName || 'head' === $this->localName || 'body' === $this->localName
                    || ('outline' === $this->localName && ('rss' !== $type || 'pie' !== $type) && 'folder' !== $type))
                    continue;

                if(!isset($this->_nodeTree[$this->depth - 1]) || 'folder' === $this->_nodeTree[$this->depth - 1])
                    $this->_folder = null;

                $this->_parentLocalName = isset($this->_nodeTree[$this->depth - 2]) ? $this->_nodeTree[$this->depth - 2] : null;
            }
        }

        $this->close();

        if($errors = libxml_get_errors())
        {
            libxml_clear_errors();
            $err = array();
            foreach($errors as $error)
            {
                if(LIBXML_ERR_FATAL === $error->level) $err[] = $error->message;
            }
            if($err)
            {
                throw new Exception(join("\n", $err), Exception::E_OWR_WARNING);
                return false;
            }
        }

        return true;
    }

    /**
     * Gets the attributes of the current node
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return boolean true on success
     */
    protected function _parseAttributes()
    {
        $attributes = $node = null;

        if($this->_itemDepth > 0)
        {
            if(!is_null($this->_parentLocalName))
            {
                if(!isset($this->_trees[$this->_parentLocalName][$this->_localName])) return false;

                $attributes =& $this->_trees[$this->_parentLocalName][$this->_localName];

                if(!isset($this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_parentLocalName]))
                    $this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_parentLocalName] = array();

                $node =& $this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_parentLocalName];
            }
            else
            {
                if(!isset($this->_trees[$this->_localName])) return false;

                $attributes =& $this->_trees[$this->_localName];

                if(!isset($this->_streams[$this->_currentStream]['item'][$this->_itemDepth]))
                    $this->_streams[$this->_currentStream]['item'][$this->_itemDepth] = array();

                $node =& $this->_streams[$this->_currentStream]['item'][$this->_itemDepth];
            }
        }
        else
        {
            if(!is_null($this->_parentLocalName))
            {
                if(!isset($this->_trees[$this->_parentLocalName]) ||
                   !isset($this->_trees[$this->_parentLocalName][$this->_localName]))
                    return false;

                $attributes =& $this->_trees[$this->_parentLocalName][$this->_localName];

                if(!isset($this->_streams[$this->_currentStream][$this->_parentLocalName]))
                {
                    $this->_streams[$this->_currentStream][$this->_parentLocalName] = array();
                    $this->_streams[$this->_currentStream][$this->_parentLocalName][$this->_localName] = array();
                    $this->_streams[$this->_currentStream][$this->_parentLocalName][$this->_localName]['attributes'] = array();
                }

                $node =& $this->_streams[$this->_currentStream][$this->_parentLocalName][$this->_localName]['attributes'];
            }
            else
            {
                if(!isset($this->_trees[$this->_localName])) return false;

                $attributes =& $this->_trees[$this->_localName];

                if(!isset($this->_streams[$this->_currentStream][$this->_localName]))
                {
                    $this->_streams[$this->_currentStream][$this->_localName] = array();
                    $this->_streams[$this->_currentStream][$this->_localName]['attributes'] = array();
                }
                $node =& $this->_streams[$this->_currentStream][$this->_localName]['attributes'];
            }
        }

        foreach($attributes as $attribute => $required)
        {
            $attrValue = $this->getAttribute($attribute);
            if($attrValue === '')
            {
                if(!$required['required']) continue;
                else
                {
                    throw new Exception(sprintf(Utilities::iGet()->_('Invalid XML : needed attribute "%s" in tag "%s"'.(!is_null($this->_parentLocalName) ? ' in parent tag "%s"' : '')), $attribute, $this->_localName, $this->_parentLocalName), Exception::E_OWR_WARNING);
                    $node[$attribute] = '';
                }
            }
            else
            {
                $node[$attribute] = $attrValue;
            }
        }

        if($this->_itemDepth > 0 && isset($this->_folder))
            $node['folder'] = $this->_folder;

        return true;
    }

    /**
     * Gets the contents of the current node
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    protected function _parseNode()
    {
        if($this->_itemDepth > 0)
        {
            if(isset($this->_parentLocalName))
            {
                if(!isset($this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_parentLocalName]))
                    $this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_parentLocalName] = array();
                if(!isset($this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_parentLocalName][$this->_localName]))
                    $this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_parentLocalName][$this->_localName] = array();

                $node =& $this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_parentLocalName][$this->_localName];
            }
            else
            {
                if(!isset($this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_localName]))
                    $this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_localName] = array();

                $node =& $this->_streams[$this->_currentStream]['item'][$this->_itemDepth][$this->_localName];
            }
        }
        else
        {
            if(isset($this->_parentLocalName))
            {
                if(!isset($this->_streams[$this->_currentStream][$this->_parentLocalName]))
                    $this->_streams[$this->_currentStream][$this->_parentLocalName] = array();
                if(!isset($this->_streams[$this->_currentStream][$this->_parentLocalName][$this->_localName]))
                    $this->_streams[$this->_currentStream][$this->_parentLocalName][$this->_localName] = array();

                $node =& $this->_streams[$this->_currentStream][$this->_parentLocalName][$this->_localName];
            }
            else
            {
                if(!isset($this->_streams[$this->_currentStream][$this->_localName]))
                    $this->_streams[$this->_currentStream][$this->_localName] = array();

                $node =& $this->_streams[$this->_currentStream][$this->_localName];
            }
        }

        if(!isset($node['contents']))
        {
            $node['contents'] = preg_replace("/<!\[CDATA\[(.*?)\]\]/", "\\1", $this->value);
        }
        else
        {
            $node['contents'] .= preg_replace("/<!\[CDATA\[(.*?)\]\]/", "\\1", $this->value);
        }
    }

    /**
     * Returns the stream(s)
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $stream the optionnal stream to return
     * @return the desired stream(s)
     */
    public function export($stream = null)
    {
        if(isset($stream))
        {
            return (!isset($this->_streams[$stream]) ? $this->_streams : $this->_streams[$stream]);
        }

        return $this->_streams;
    }
}