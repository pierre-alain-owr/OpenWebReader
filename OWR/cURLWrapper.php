<?php
/**
 * cURL Wrapper class
 * Class used to get remote contents
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
 * This object is used to get remote contents
 * It will try to use cURL if enabled, else file_get_contents
 * @package OWR
 */
class cURLWrapper
{
    /**
     * @var float total requests time
     * @access protected
     * @static
     */
    static protected $_httpTime = 0;

    /**
     * Try to use cURL if enabled to get distant url
     * else uses file_get_contents
     * It will set $headers from the response
     * 
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @static
     * @access public
     * @param string $url the url to get
     * @param array $opts $options for cURL, optionals
     * @param bool $isXML if we must treat the response as xml
     * @param bool $noCache must-we check the cache ? (cURL only)
     * @param array &$headers headers from the response
     * @return mixed the response on succes, else false
     */
    static public function get($url, array $opts=array(), $isXML = true, $noCache = false, &$headers = array())
    {
        $headers = array();
        $values = @parse_url($url);
        if(false === $values || ((!isset($values['scheme']) || 'file' === $values['scheme']) && 
        (!($url = realpath($url)) || 0 !== mb_strpos($url, Config::iGet()->get('defaultTmpDir'))))) 
            return false;
    
        if(!extension_loaded('curl'))
        {
            $time = microtime(true);
            $heads = "Accept-Charset: utf-8\n".
                        "User-Agent: OpenWebReader/".Config::iGet()->get('version')." http://openwebreader.org - Free Web-Based Feed Aggregator\n"; // some ad :d

            if(!$noCache)
            {
                $cacheFilename = md5($url);
    
                if($cache = Cache::getFromCache('curl_'.$cacheFilename))
                {
                    !isset($cache['Etag']) || ($heads .= 'If-None-Match: '.$cache['Etag']."\n");
                    !isset($cache['Last-Modified']) || ($heads .= 'If-Modified-Since: '.$cache['Last-Modified']."\n");
                }
            }

            $ret = @file_get_contents($url, null, 
                        stream_context_create(array('http' => array('header' => $heads)))
                );
    
            self::$_httpTime += (float)round(microtime(true) - $time, 6);
    
            if(false === $ret)
            {
                Logs::iGet()->log($url.': file_get_contents failed !', Exception::E_OWR_WARNING);
                return false;
            }

            $headers = self::_parseHeader($http_response_header);

            if(!$noCache)
            {
                if(isset($headers['Status']) && false !== strpos($headers['Status'], 304)) return '';

                $cache = array();
    
                foreach(array('Etag', 'Last-Modified') as $key)
                {
                    !isset($headers[$key]) || $cache[$key] = $headers[$key];
                }
    
                if(!empty($cache))
                {
                    Cache::writeToCache('curl_'.$cacheFilename, $cache);
                }
            }
        }
        else
        {
            if(isset($values['user']))
            {
                $userpwd = $values['user'].':'.(isset($values['pass']) ? $values['pass'] : '');
                $opts += array(CURLOPT_USERPWD => $userpwd);
                $url = str_replace($userpwd, '', $url);
                unset($userpwd);
            }
        
            $ch = curl_init();
            if(!$ch)
            {
                return false;
            }
            
            $opts += array(
                    CURLOPT_HEADER          => true,
                    CURLOPT_FOLLOWLOCATION  => true,
                    CURLOPT_RETURNTRANSFER  => true,
                    CURLOPT_BINARYTRANSFER  => true,
                    CURLOPT_FAILONERROR     => true,
                    CURLOPT_HTTPAUTH        => CURLAUTH_ANY,
                    CURLOPT_USERAGENT       => 'OpenWebReader/'.Config::iGet()->get('version').' http://openwebreader.org - Free Web-Based Feed Aggregator', // some ad :d
                    CURLOPT_URL             => $url,
                    CURLOPT_HTTPHEADER      => (array)"Accept-Charset: utf-8",
                    CURLOPT_SSL_VERIFYPEER  => false,
                    CURLOPT_SSL_VERIFYHOST  => 2,
                    CURLOPT_TIMEOUT         => 30 // max 30s for timeout, just enough isn't it ?
                    );
            
            if(!$noCache)
            {
                $cacheFilename = md5($url);
    
                if($cache = Cache::getFromCache('curl_'.$cacheFilename))
                {
                    !isset($cache['Etag']) || $opts[CURLOPT_HTTPHEADER][] = 'If-None-Match: '.$cache['Etag'];
                    !isset($cache['Last-Modified']) || $opts[CURLOPT_HTTPHEADER][] = 'If-Modified-Since: '.$cache['Last-Modified'];
                }
            }

            if(!curl_setopt_array($ch, $opts)) return false;
            
            $resp = curl_exec($ch);
            $infos = curl_getinfo($ch);

            if(isset($infos['total_time'])) self::$_httpTime += (float)$infos['total_time'];
            
            if(false === $resp)
            {
                Logs::iGet()->log($url.':'.curl_error($ch), Exception::E_OWR_WARNING);
                return false;
            }

            curl_close($ch);

            if(!$noCache)
            {
                if(isset($infos['http_code']) && 304 === (int)$infos['http_code'])
                {
                    return '';
                }
            }

            $ret = trim(substr($resp, $infos['header_size']));
            $headers = self::_parseHeader(explode("\n", trim(substr($resp, 0, $infos['header_size']))));
            unset($resp);

            if(!$noCache)
            {
                $cache = array();
    
                foreach(array('Etag', 'Last-Modified') as $key)
                {
                    !isset($headers[$key]) || $cache[$key] = $headers[$key];
                }
    
                if(!empty($cache))
                {
                    Cache::writeToCache('curl_'.$cacheFilename, $cache);
                }
            }
        }
        
        if($isXML)
        {
            $ret = Strings::toNormal($ret); // need to do it BEFORE utf8_encode
            
            if(false !== stripos($ret, '<?xml'))
                $ret = preg_replace("/<\?xml\b([^>]+)encoding=([\"'])(?!utf-8)(.*?)(\\2)([^>]*)\?>/si", "<?xml\\1encoding=\"utf-8\"\\5?>", $ret);
                
            if('UTF-8' !== mb_detect_encoding($ret, 'utf-8,iso-8859-15,iso-8859-1', true))
            {
                $ret = utf8_encode($ret);
            }
        }
    
        return trim($ret);
    }

    /**
     * Parse response header's and return an associative array
     * 
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @static
     * @access protected
     * @param array $headers the headers to parse
     * @return array the headers
     */
    static protected function _parseHeader(array $headers)
    {
        $currHeaders = array();

        foreach($headers as $header)
        {
            $header = trim($header);
            if(0 === strpos($header, 'HTTP/1'))
            {
                $currHeaders['Status'] = $header;
            }
            else
            {
                $header = explode(': ', $header, 2);
                if(count($header) > 1)
                    $currHeaders[$header[0]] = $header[1];
            }
        }

        return $currHeaders;
    }

    /**
     * Returns the added microtime of all HTTP queries
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed string or array to sanitize
     * @return float $_httpTime
     */
    static public function getTime()
    {
        return (float) self::$_httpTime;
    }
}