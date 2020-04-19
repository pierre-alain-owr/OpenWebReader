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
namespace OWR\Cache;
use OWR\View\Utilities,
    OWR\Interfaces\Cache,
    OWR\Logs;
/**
 * This object manages cache files
 * @package OWR
 */
class CFile implements Cache
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
     * @param string $dir a directory in cache/
     * @param boolean $maintenance must-we just check for the lastmtime ?
     * @return int number of deleted files
     */
    static public function clear($dir = '', $maintenance = false)
    {
        $dir = HOME_PATH.'cache'.DIRECTORY_SEPARATOR.(string)$dir;

        $nb = 0;

        clearstatcache();

        if(!file_exists($dir)) return $nb;

        $cache = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        if($maintenance)
        {
            $now = Config::iGet()->get('begintime');
            $cacheTime = Config::iGet()->get('cacheTime');
        }

        foreach($cache as $file)
        {
            if(!$cache->isDot() && !$cache->isDir() && $cache->isWritable())
            {
                if($maintenance && ($cache->getMTime() + $cacheTime < $now))
                {
                    continue;
                }

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
    static public function clearDB()
    {
        return self::clear('db');
    }

    /**
     * Delete every files found in HTML cache directories
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @static
     */
    static public function clearHTML()
    {
        !file_exists(HOME_PATH.'cache'.DIRECTORY_SEPARATOR.'translations_fr_FR') || @unlink(HOME_PATH.'cache'.DIRECTORY_SEPARATOR.'translations_fr_FR');
        !file_exists(HOME_PATH.'cache'.DIRECTORY_SEPARATOR.'translations_en_US') || @unlink(HOME_PATH.'cache'.DIRECTORY_SEPARATOR.'translations_en_US');
        return (self::clear('fr_FR') + self::clear('en_US'));
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
    static public function get($filename, $cacheTime=0)
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
    static public function write($filename, $datas)
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
            if(!is_writeable($dir))
            {
                Logs::iGet()->log(sprintf(Utilities::iGet()->_('The directory "%s" is not writeable'), $dir));
                return false;
            }
        }
        elseif(file_exists($dir))
        {
            if(!@unlink($dir))
            {
                Logs::iGet()->log(sprintf(Utilities::iGet()->_('The file "%s" exists, is not a dir and can not be removed'), $dir));
                return false;
            }

        }
        else
        {
            if(is_writeable(HOME_PATH.'cache'))
            {
                if(!@mkdir($dir))
                { // hu ?
                    Logs::iGet()->log(sprintf(Utilities::iGet()->_('Can not create the directory "%s"'), $dir));
                    return false;
                }
            }
            else
            {
                Logs::iGet()->log(Utilities::iGet()->_('The directory "%s" is not writeable', HOME_PATH.'cache'));
                return false;
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
    static public function getRandomFilename($tmp = false)
    {
        $dir = $tmp ? Config::iGet()->get('defaultTmpDir') : PATH.'cache/';
        return tempnam($dir, 'OWR');
    }
}
