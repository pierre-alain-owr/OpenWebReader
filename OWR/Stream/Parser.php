<?php
/**
 * Stream parser class
 * This class extends XMLReader and is used to parse rss, atom, rdf, dc streams
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
use \XMLReader,
    OWR\cURLWrapper,
    OWR\Exception,
    OWR\DAO,
    OWR\Strings,
    OWR\View\Utilities;
/**
 * This object is used to parse stream (rss, atom, rdf, dc)
 * @uses Strings xml entities and M$ bad chars conversion
 * @uses Exception the exceptions handler
 * @uses StreamReader the parsed stream
 * @uses Cache check HTMLPurifier cache directory
 * @uses cURLWrapper get the stream source
 * @uses OWR\View\Utilities translate errors
 * @package OWR
 * @subpackage Stream
 */
class Parser extends XMLReader
{
    /**
    * @var array parsed stream
    * @access private
    */
    private $_stream;

    /**
    * @var array list of streams structure (rss, atom, dc, rdf)
    * @access private
    */
    private $_trees;

    /**
    * @var int number of items in stream
    * @access private
    */
    private $_itemCount;

    /**
    * @var boolean current stream is atom
    * @access private
    */
    private $_isAtom;

    /**
    * @var boolean current stream is rdf
    * @access private
    */
    private $_isRDF;

    /**
    * @var string host of the stream
    * @access private
    */
    private $_currentHost;

    /**
    * @var mixed instance of HTMLPurifier
    * @access private
    * @static
    */
    static private $_filter;

    /**
    * @var array nodes tree
    * @access private
    */
    private $_nodeTree;

    /**
    * @var string current node name
    * @access private
    */
    private $_localName;

    /**
    * @var array current node
    * @access private
    */
    private $_currentNode;

    /**
    * @var float time of stream parsing
    * @access protected
    * @static
    */
    static protected $_parseTime = 0;

    /**
    * @var int number of parsed streams
    * @access protected
    * @static
    */
    static protected $_nbParsedStreams = 0;

    /**
     * Constructor
     * Create the filter if not already done and defines the differents streams' trees
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __construct()
    {
        if(!isset(self::$_filter))
        {
            class_exists('\HTMLPurifier', false) || include HOME_PATH.'libs/HTMLPurifier/HTMLPurifier.standalone.php';
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Core.Encoding', 'UTF-8');
            $config->set('HTML.TidyLevel', 'heavy' );
            $config->set('Cache.SerializerPath', HOME_PATH.'cache');
            $config->set('HTML.Doctype', 'XHTML 1.0 Strict');
            $config->set('HTML.ForbiddenAttributes', '*@style');
            $config->set('HTML.AllowedElements', 'p,i,b,br,em,strong,span,blockquote,code,h1,h2,h3,h4,h5,h6,a,img,ul,li,ol,dd,dt');
            $config->set('HTML.SafeObject', true);
            $config->set('HTML.SafeEmbed', true);
            self::$_filter = new \HTMLPurifier($config);
            unset($config);
        }

        $this->_currentHost = '';

        $this->_trees = array();

        $this->_trees['atom'] = array(
            'id'                => array('required' => true),
            'title'             => array('required' => true),
            'updated'           => array('required' => true),
            'author'            => array('required' => false),
            'link'              => array('required' => false),
            'category'          => array('required' => false),
            'contributor'       => array(
                'required'  => false,
                'name'      => true,
                'uri'       => false,
                'email'     => false),
            'generator'         => array('required' => false),
            'icon'              => array('required' => false),
            'logo'              => array('required' => false),
            'rights'            => array('required' => false),
            'subtitle'          => array('required' => false),
            'item'             => array(
                'id'                => array('required' => true),
                'title'             => array('required' => true),
                'updated'           => array('required' => true),
                'author'            => array(
                    'required'  => false,
                    'name'      => true,
                    'uri'       => false,
                    'email'     => false),
                'content'           => array('required' => false),
                'link'              => array(
                    'required'  => false,
                    'rel'       => false,
                    'href'      => true,
                    'type'      => false,
                    'hreflang'  => false,
                    'title'     => false,
                    'length'    => false),
                'summary'           => array('required' => false),
                'category'          => array(
                    'required'  => false,
                    'term'      => true,
                    'scheme'    => false,
                    'label'     => false),
                'contributor'       => array('required' => false),
                'published'         => array('required' => false),
                'source'            => array('required' => false),
                'rights'            => array('required' => false),
                )
            );

        $this->_trees['rss'] = array(
            'title'             => array('required' => true),
            'link'              => array('required' => true),
            'description'       => array('required' => true),
            'language'          => array('required' => false),
            'copyright'         => array('required' => false),
            'managingEditor'    => array('required' => false),
            'webMaster'         => array('required' => false),
            'pubDate'           => array('required' => false),
            'lastBuildDate'     => array('required' => false),
            'category'          => array('required' => false),
            'generator'         => array('required' => false),
            'docs'              => array('required' => false),
            'cloud'             => array(
                'required'          => false,
                'domain'            => true,
                'port'              => true,
                'path'              => true,
                'registerProcedure' => true,
                'protocol'          => true),
            'ttl'               => array('required' => false),
            'image'             => array(
                'required'      => false,
                'url'           => true,
                'title'         => true,
                'link'          => true,
                'width'         => false,
                'height'        => false,
                'description'   => false),
            'rating'            => array('required' => false),
            'textInput'         => array(
                'required'      => false,
                'title'         => true,
                'description'   => true,
                'name'          => true,
                'link'          => true),
            'skipHours'         => array('required' => false),
            'skipDays'          => array('required' => false),

            'item'      => array(
                'title'         => array('required' => false),
                'link'          => array('required' => false),
                'description'   => array('required' => true),
                'author'        => array('required' => false),
                'category'      => array(
                    'required'      => false,
                    'domain'        => false),
                'comments'      => array('required' => false),
                'enclosure'     => array(
                    'required'      => false,
                    'url'           => true,
                    'length'        => true,
                    'type'          => true),
                'guid'          => array(
                    'required'      => false,
                    'isPermaLink'   => false),
                'pubDate'       => array('required' => false),
                'source'        => array(
                    'required'      => false,
                    'url'           => true)
            )
        );

        $this->_trees['rdf'] = array(
            'channel'           => array(
                'title'             => array('required' => true),
                'link'              => array('required' => true),
                'description'       => array('required' => true),
                'updatePeriod'      => array('required' => true),
                'updateFrequency'   => array('required' => true),
                'updateBase'        => array('required' => true),
                'image'             => array('required' => false,
                                             'resource' => true),
                'items'             => array('required' => true,
                                             'Seq'      => array('required' => true,
                                                                  'li'       => array(
                                                                    'required' => true,
                                                                    'resource' => true)))),
            'image'             => array(
                'required'      => false,
                'url'           => true,
                'title'         => true,
                'link'          => true,
                'about'         => true),
            'item'      => array(
                'title'         => array('required' => false),
                'link'          => array('required' => false),
                'description'   => array('required' => true)
            )
        );

        $this->_trees['dc'] = array(
            'title'         => 'title',
            'creator'       => 'creator',
            'subject'       => 'keywords',
            'description'   => 'description',
            'publisher'     => 'publisher',
            'contributor'   => 'contributor',
            'date'          => 'pubDate',
            'type'          => 'type',
            'format'        => 'format',
            'identifier'    => 'identifier',
            'source'        => 'source',
            'language'      => 'language',
            'relation'      => 'relation',
            'coverage'      => 'coverage',
            'rights'        => 'rights'
        );
    }

    /**
     * Returns the parsed stream
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return mixed the stream (as a StreamReader object) if exists, or false
     */
    public function export()
    {
        if(!empty($this->_stream['channel']['version']))
        {
            $ret = new Reader($this->_stream);
            $this->_stream = null; // reset from memory
            return $ret;
        } else return false;
    }

    /**
     * Returns the added microtime of all streams parsing time
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return float $_parseTime
     */
    static public function getTime()
    {
        return (float) self::$_parseTime;
    }

    /**
     * Returns the number of parsed streams
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return float $_nbParsedStreams
     */
    static public function getCount()
    {
        return (int) self::$_nbParsedStreams;
    }

    /**
     * Returns the source of the remote stream
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $url the url of the stream
     * @return string the source
     */
    public function getSrc($url)
    {
        if(!$this->_getUri($url)) return false;

        $src = @cURLWrapper::get($url);

        if(false === $src)
        {
            $dao = DAO::getCachedDAO('streams')->get(array('url' => $url));
            if($dao) $dao->declareUnavailable();
            throw new Exception(sprintf(Utilities::iGet()->_('Aborting parsing of stream "%s" and declaring it as unavailable : can\'t get the content'), $url), Exception::E_OWR_WARNING);
        }

        return $src;
    }

    /**
     * Sets the host of the stream
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $uri the uri to parse
     */
    private function _getUri($uri)
    {
        $url = @parse_url((string) $uri);

        if(false === $url || !isset($url['scheme']) || 'file' === $url['scheme'])
        {
            throw new Exception(sprintf(Utilities::iGet()->_('Invalid uri "%s"'), $uri), Exception::E_OWR_WARNING);
        }

        $this->_currentHost = $url['scheme'].'://'.$url['host'];
        return true;
    }

    /**
     * Parse the stream
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $uri the uri of the stream to parse
     * @param string $src the source, optionnal
     * @return boolean true on success
     */
    public function parse($uri, $src='')
    {
        if(!$src)
        {
            $src = $this->getSrc($uri);
        }
        elseif(!$this->_currentHost)
        {
            $this->_getUri($uri);
        }

        $src = Strings::toXML($src, false, false);

        if(!$src) return false;

        $microtime = microtime(true);

        libxml_use_internal_errors(true);

        if(!@$this->XML($src, 'UTF-8', LIBXML_NOBLANKS | LIBXML_NOCDATA))
        {
            unset($src);
            throw new Exception('Invalid stream', Exception::E_OWR_WARNING);
        }

        $this->_nodeTree = array();
        $this->_stream = array('channel'=>array('version'=>''), 'item'=>array(), 'src'=>$src);
        unset($src);
        $this->_itemCount = 0;
        $this->_isAtom = $this->_header = $this->_isRDF = $this->_isRSS = $this->_isItem = false;
        $this->_currentNode = $this->_localName = null;

        while(@$this->read())
        {
            if(self::ELEMENT === $this->nodeType)
            { // opening tag
                // rss
                if('rss' === $this->localName)
                {
                    $this->_stream['channel']['version'] = 'rss';
                    $this->_isRSS = true;
                    continue;
                }
                // atom
                elseif('feed' === $this->localName)
                {
                    $this->_stream['channel']['version'] = 'atom';
                    $this->_isAtom = true;
                    continue;
                }
                // rdf
                elseif('rdf' === $this->localName || 'RDF' === $this->localName)
                {
                    $this->_stream['channel']['version'] = 'rdf';
                    $this->_isRDF = true;
                    continue;
                }
                // channel description
                elseif('channel' === $this->localName)
                {
                    continue;
                }
                // don't care about sequence
                elseif('seq' === strtolower($this->localName))
                {
                    while(@$this->read() && 'seq' !== strtolower($this->localName));
                    continue;
                }
                // encapsuled HTML
                elseif($this->prefix === 'html' || $this->prefix === 'xhtml' ||
                    'http://www.w3.org/1999/xhtml' === $this->namespaceURI ||
                    'http://www.w3.org/1999/xhtml/' === $this->namespaceURI)
                {
                    $value = $this->_parseHTML();
                    $this->_clean($value);
                    if(!isset($this->_currentNode['contents']))
                    {
                        $this->_currentNode['contents'] = $value;
                    }
                    elseif(is_array($this->_currentNode['contents']))
                    {
                        $this->_currentNode['contents'][] = $value;
                    }
                    else
                    {
                        $prev = $this->_currentNode['contents'];
                        $this->_currentNode['contents'] = array();
                        $this->_currentNode['contents'][] = $prev;
                        unset($prev);
                        $this->_currentNode['contents'][] = $value;
                    }
                    unset($value);
                    continue;
                }
                // item beginning
                elseif('item' === $this->localName || 'entry' === $this->localName)
                {
                    $this->_isItem = true;
                    $this->_localName = 'item';
                    ++$this->_itemCount;
                }
                // dublin core
                elseif($this->prefix === 'dc' || 'dublincore' === $this->prefix ||
                    'http://purl.org/dc/elements/1.1/' === $this->namespaceURI ||
                    'http://purl.org/dc/elements/1.1' === $this->namespaceURI)
                {
                    if(isset($this->_trees['dc'][$this->localName]))
                    { // known internal equivalent
                        $this->_localName = $this->_trees['dc'][$this->localName];
                    }
                    else
                    {
                        $this->_localName = $this->localName;
                    }
                }
                else
                {
                    $this->_localName = $this->localName;
                }

                if('item' === $this->_localName)
                {
                    $this->_stream['item'][$this->_itemCount] = array();
                    continue;
                }
                elseif($this->isEmptyElement)
                {
                    if($this->hasAttributes)
                    {
                        $attributes = array();
                        $this->moveToFirstAttribute();
                        do
                        {
                            if($this->hasValue)
                            {
                                isset($attributes[$this->localName]) || $attributes[$this->localName] = array();
                                $attributes[$this->localName][] = $this->value;
                            }
                        }
                        while($this->moveToNextAttribute());

                        $this->moveToElement();

                        if(!empty($attributes))
                        {
                            if($this->_isItem)
                            {
                                $this->_currentNode =& $this->_stream['item'][$this->_itemCount];
                            }
                            else
                            {
                                $this->_currentNode =& $this->_stream['channel'];
                            }

                            $this->_nodeTree[] = $this->_localName;
                            foreach($this->_nodeTree as $tree)
                            {
                                isset($this->_currentNode[$tree]) || $this->_currentNode[$tree] = array();
                                $this->_currentNode =& $this->_currentNode[$tree];
                            }
                            $this->_currentNode =& $this->_currentNode[];
                            $this->_currentNode['attributes'] = array();
                            $this->_currentNode =& $this->_currentNode['attributes'];
                            foreach($attributes as $attribute=>$values)
                            {
                                isset($this->_currentNode[$attribute]) || $this->_currentNode[$attribute] = array();
                                if(count($values) > 1)
                                {
                                    foreach($values as $value)
                                    {
                                        $node =& $this->_currentNode[$attribute][];
                                        $node = $value;
                                    }
                                }
                                else
                                {
                                    $node =& $this->_currentNode[$attribute];
                                    $node = $values[0];
                                }
                            }
                        }
                        unset($attributes);
                    }
                    array_pop($this->_nodeTree);
                    continue;
                }
                elseif($this->_isItem)
                {
                    $this->_currentNode =& $this->_stream['item'][$this->_itemCount];
                    $this->_nodeTree[] = $this->_localName;
                    foreach($this->_nodeTree as $tree)
                    {
                        isset($this->_currentNode[$tree]) || $this->_currentNode[$tree] = array();
                        $this->_currentNode =& $this->_currentNode[$tree];
                    }
                }
                else
                {
                    $this->_currentNode =& $this->_stream['channel'];
                    $this->_nodeTree[] = $this->_localName;
                    foreach($this->_nodeTree as $tree)
                    {
                        isset($this->_currentNode[$tree]) || $this->_currentNode[$tree] = array();
                        $this->_currentNode =& $this->_currentNode[$tree];
                    }
                }

                if($this->hasAttributes)
                {
                    $attributes = array();
                    $this->moveToFirstAttribute();
                    do
                    {
                        if($this->hasValue)
                        {
                            isset($attributes[$this->localName]) || $attributes[$this->localName] = array();
                            $attributes[$this->localName][] = $this->value;
                        }
                    }
                    while($this->moveToNextAttribute());

                    $this->moveToElement();

                    if(!empty($attributes))
                    {
                        $this->_currentNode['attributes'] = array();
                        foreach($attributes as $attribute=>$values)
                        {
                            isset($this->_currentNode['attributes'][$attribute]) || $this->_currentNode['attributes'][$attribute] = array();
                            if(count($values) > 1)
                            {
                                foreach($values as $value)
                                {
                                    $node =& $this->_currentNode['attributes'][$attribute][];
                                    $node = $value;
                                }
                            }
                            else
                            {
                                $node =& $this->_currentNode['attributes'][$attribute];
                                $node = $values[0];
                            }
                        }
                    }
                    unset($attributes);
                }
            }
            elseif(self::TEXT === $this->nodeType || self::CDATA === $this->nodeType)
            { // tag contents
                if($this->hasValue)
                {
                    $value = $this->value;
                    $this->_clean($value);
                    if(!isset($this->_currentNode['contents']))
                    {
                        $this->_currentNode['contents'] = $value;
                    }
                    elseif(is_array($this->_currentNode['contents']))
                    {
                        $this->_currentNode['contents'][] = $value;
                    }
                    else
                    {
                        $prev = $this->_currentNode['contents'];
                        $this->_currentNode['contents'] = array();
                        $this->_currentNode['contents'][] = $prev;
                        unset($prev);
                        $this->_currentNode['contents'][] = $value;
                    }
                    unset($value);
                }
            }
            elseif(self::END_ELEMENT === $this->nodeType)
            { // closing tag
                if('channel' === $this->localName || 'rss' === $this->localName ||
                    'feed' === $this->localName || 'rdf' === $this->localName || 'RDF' === $this->localName)
                    continue;
                elseif('item' === $this->localName || 'entry' === $this->localName)
                {
                    $this->_isItem = false;
                    if(empty($this->_stream['item'][$this->_itemCount]['link']))
                        unset($this->_stream['item'][$this->_itemCount]); // no link, grrr but don't want it, TODO ??
                }

                array_pop($this->_nodeTree);
            }
        }

        $this->close();

        self::$_parseTime += (float) (microtime(true) - $microtime);
        ++self::$_nbParsedStreams;

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
                throw new Exception($uri.': '.join("\n", $err), Exception::E_OWR_NOTICE);
                return false;
            }
        }

        return true;
    }

    /**
     * Parse XML like HTML
     *
     * @access private
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return string $html
     */
    private function _parseHTML()
    {
        $tag = $this->localName;
        $html = '<'.$tag;
        if($this->hasAttributes)
        {
            $this->moveToFirstAttribute();
            do
            {
                $attrValue = $this->value;
                if($attrValue)
                {
                    $html .= ' '.$this->localName.'="'.$attrValue.'"';
                }
            }
            while($this->moveToNextAttribute());
            $this->moveToElement();
        }

        if($this->isEmptyElement)
        {
            $html .= ' />';
        }
        else
        {
            $html .= '>';

            if($this->hasValue)
                $html .= $this->value;

            while(@$this->read() && $this->localName !== $tag)
            {
                if(self::ELEMENT === $this->nodeType)
                {
                    $html .= $this->_parseHTML();
                }
                elseif(self::TEXT === $this->nodeType || self::CDATA === $this->nodeType)
                {
                    $html .= $this->value;
                }
                elseif(self::END_ELEMENT === $this->nodeType)
                {
                    $html .= '</'.$this->localName.'>';
                }
            }
            $html .= '</'.$tag.'>';
        }

        return $html;
    }

    /**
     * Cleaning function
     * This function replaces some HTML and purify value
     *
     * @access private
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $data the data to clean
     */
    private function _clean(&$data)
    {
//         $data = nl2br(trim((string) $data));
        $data = trim((string) $data);
/*        $data = preg_replace(   array(  "/<!\[CDATA\[(.*?)\]\]/is", // theorically useless here
                                        '/(<img\b[^>]*)(src\s*=\s*([\'"])?((?!https?:\/\/).*?)\\3\s*)([^>]*)\/?>/si',
                                        "/<div([^>]*>.*?)<\/div>/si"
                                ),
                                array(  "\\1",
                                        "\\1src=\"".$this->_currentHost."\\4\"\\5/>",
                                        "<p\\1</p>"
                                ), $data);*/
        $data = preg_replace(   array(  "/<!\[CDATA\[(.*?)\]\]/is", // theorically useless here
                                        '/(<img\b[^>]*)(src\s*=\s*([\'"])?((?!https?:\/\/).*?)\\3\s*)([^>]*)\/?>/si', // full uri
                                        '/(<a\b[^>]*)(href\s*=\s*([\'"])?((?!https?:\/\/).*?)\\3\s*)([^>]*)>/si', // full uri
//                                         "/<div([^>]*>.*?)<\/div>/si"
                                ),
                                array(  "\\1",
                                        "\\1src=\"".$this->_currentHost."\\4\"\\5/>",
                                        "\\1href=\"".$this->_currentHost."\\4\"\\5/>",
//                                         "<p\\1</p>"
                                ), $data);
        $data = static::$_filter->purify($data);
    }
}
