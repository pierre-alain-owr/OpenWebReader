<?php
/**
 * Cache class
 * Class for cache tools utility
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
 * This object manages cache files
 * @package OWR
 */
class Cache
{
    /**
     * Constructor
     *
     * @access private
     */
    private function __construct() {}

    /**
     * Delete every files found in cache directory
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     * @param $dir a directory in cache/
     */
    static public function clearCache($dir = '')
    {
        $dir = HOME_PATH.'cache'.DIRECTORY_SEPARATOR.(string)$dir;
        
        $nb = 0;
        if(!file_exists($dir)) return $nb;

        $cache = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach($cache as $file) 
        {
            if(!$cache->isDot() && !$cache->isDir() && $cache->isWritable()) 
            {
                $nb += (int) unlink((string) $file);
            }
        }

        return $nb;
    }

    /**
     * Delete every files found in DB cache directory
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     */
    static public function clearDBCache()
    {
        return self::clearCache('db');
    }

    /**
     * Delete every files found in HTML cache directories
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     */
    static public function clearHTMLCache()
    {
        !file_exists(HOME_PATH.'cache'.DIRECTORY_SEPARATOR.'translations_fr_FR') || @unlink(HOME_PATH.'cache'.DIRECTORY_SEPARATOR.'translations_fr_FR');
        !file_exists(HOME_PATH.'cache'.DIRECTORY_SEPARATOR.'translations_en_US') || @unlink(HOME_PATH.'cache'.DIRECTORY_SEPARATOR.'translations_en_US');
        return (self::clearCache('fr_FR') + self::clearCache('en_US'));
    }

    /**
     * Try to get serialized datas from cache
     * This function uses file locking
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @static
     * @param string $filename cache file name
     * @param int $cacheTime cache life time
     * @return mixed
     * @access public
     */
    static public function getFromCache($filename, $cacheTime=0)
    {
        $filename = HOME_PATH.'cache'.DIRECTORY_SEPARATOR.$filename;
    
        $cacheTime = (int)$cacheTime;
    
        if(!file_exists($filename) || ($cacheTime > 0 && (time() > (@filemtime($filename) + $cacheTime))))
            return false;
    
        if(!($fh = @fopen($filename, 'rb'))) return false;
        
        @flock($fh, LOCK_SH);
        $datas = @stream_get_contents($fh);
        @fclose($fh);
        
        if(false === $datas) return false;
        
        return (@unserialize(base64_decode($datas, true)));
    }
    
    /**
     * Try to write serialized datas into cache
     * This function uses file locking
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @static
     * @param string $filename cache file name
     * @param string $datas datas to store in cache
     * @return mixed
     * @access public
     */
    static public function writeToCache($filename, $datas)
    {
        $filename = HOME_PATH.'cache'.DIRECTORY_SEPARATOR.$filename;
        
        $dir = dirname($filename);
        if(!is_dir($dir)) 
        {
            if(file_exists($dir) && !@unlink($dir)) return false;
            if(!@mkdir($dir)) return false;
        }
        
        $fh = @fopen($filename, 'w+b');
        if(!$fh) return false;
        
        @flock($fh, LOCK_EX);
        $ret = @fwrite($fh, base64_encode(serialize($datas)));
        @fclose($fh);
            
        return $ret;
    }

    /**
     * Check that the directory is writeable
     * It will try to create it if it does not exists
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @static
     * @param string $dir the directory's name
     * @return mixed
     * @access public
     */
    static public function checkDir($dir)
    {
        $dir = HOME_PATH.'cache'.DIRECTORY_SEPARATOR.$dir;
        
        if(is_dir($dir))
        {
            if(!is_writeable($dir)) throw new Exception('The directory '.$dir.' is not writeable');
        }
        elseif(file_exists($dir))
        {
            if(!@unlink($dir)) throw new Exception('The file '.$dir.' exists, is not a dir and can not be removed');
        }
        else
        {
            if(is_writeable(HOME_PATH.'cache'))
            {
                if(!@mkdir($dir))
                { // hu ?
                    throw new Exception('Can not create the directory '.$dir);
                }
            } 
            else 
            {
                throw new Exception('The directory '.HOME_PATH.'cache is not writeable');
            }
        }

        return true;
    }


    /**
     * Returns a unique filename
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $tmp if we must use default tmp dir, default to false
     * @return string the file name
     * @access public
     */
    static public function getRandomFilename($tmp=false)
    {
        $dir = $tmp ? Config::iGet()->get('defaultTmpDir') : PATH.'cache/';
        return tempnam($dir, 'OWR');
    }
}