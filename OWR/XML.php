<?php
/**
 * Object used to manipulate XML
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
 * This object is usefull for XML manipulation
 *
 * @uses Strings string usefull tools
 * @package OWR
 */
class XML
{
    /**
     * Serialize an array to xml
     *
     * @static
     * @access public
     * @param array $arr the array to convert
     * @param string $rootEl name of the root element
     * @param int $statusCode the status code of the response, optional
     * @param mixed $dom \DOMDocument node, recursion
     * @param mixed $domel \DOMElement node, recursion
     * @return string the xml representation of $arr
     */
    static public function serialize(array $arr, $rootEl = '', $statusCode = 0, \DOMDocument $dom = null, \DOMElement $domel = null)
    {
        if(isset($dom))
        {
            foreach($arr as $k=>$v)
            {
                if(is_numeric($k)) $k = '_'.$k;
                else
                {
                    $k = preg_replace('/[^a-z0-9_]/i', '_', $k);
                }

                if(is_array($v))
                {
                    $element = $dom->createElement($k);
                    self::serialize($v, '', 0, $dom, $element);
                    $domel->appendChild($element);
                }
                else
                {
                    $domel->appendChild($dom->createElement($k, Strings::toXML($v)));
                }
            }
        }
        else
        {
            $dom = new \DOMDocument('1.0', 'utf-8');
            $el = $dom->createElement($rootEl);
            empty($statusCode) || $el->setAttribute('status', (int) $statusCode);
            $dom->appendChild($el);
            foreach($arr as $k=>$v)
            {
                if(is_numeric($k)) $k = '_'.$k;
                else
                {
                    $k = preg_replace('/[^a-z0-9_]/i', '_', $k);
                }

                if(is_array($v))
                {
                    $element = $dom->createElement($k);
                    self::serialize($v, '', 0, $dom, $element);
                    $el->appendChild($element);
                }
                else
                {
                    $el->appendChild($dom->createElement($k, Strings::toXML($v)));
                }
            }
            $dom->formatOutput = true;
            return $dom->saveXML();
        }
    }

    /**
     * Unserialize xml string to associative array
     *
     * @static
     * @access public
     * @param array $xml the xml string to convert
     * @param mixed $reader \XMLReader the xmlreader instance, recursion
     * @param array &$arr the associative array, recursion
     * @param string $localName the current node name
     * @return array the array representing $xml, false on error
     */
    static public function unserialize($xml = '', \XMLReader $reader = null, &$arr = array(), $localName = '')
    {
        if(!isset($reader))
        {
            libxml_use_internal_errors(true);

            $reader = new \XMLReader();

            if(!@$reader->XML($xml, 'UTF-8', LIBXML_NOBLANKS | LIBXML_NOCDATA))
            {
                return false;
            }
        }

        if(!empty($localName))
        {
            isset($arr[$localName]) || $arr[$localName] = array();
            $node =& $arr[$localName];
        }
        else $node =& $arr;

        while(@$reader->read())
        {
            if(\XMLReader::ELEMENT === $reader->nodeType)
            {
                if($reader->isEmptyElement && !$reader->hasValue) continue;

                $localName = $reader->localName;
                if(0 === mb_strpos($localName, '_', 0, 'UTF-8'))
                    $localName = mb_substr($localName, 1, mb_strlen($localName, 'UTF-8'), 'UTF-8');
                isset($node[$localName]) || $node[$localName] = null;
                if(!$reader->isEmptyElement)
                    self::unserialize(null, $reader, $node, $localName);
            }
            elseif(\XMLReader::END_ELEMENT === $reader->nodeType)
            {
                $node =& $arr;
            }
            elseif(\XMLReader::TEXT === $reader->nodeType)
            {
                is_string($node) || $node = '';
                $node .= $reader->value;
            }
        }

        return $node;
    }
}