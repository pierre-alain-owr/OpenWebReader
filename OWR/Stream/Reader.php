<?php
/**
 * StreamReader class
 * This class represents a stream
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
use OWR\Config as Config;
/**
 * This object is used to deal with a parsed stream
 * @uses StreamItem an item from the stream
 * @package OWR
 * @subpackage Stream
 */
class Reader
{
    /**
    * @var array the stream
    * @access private
    */
    private $_stream;

    /**
    * @var string version of stream (rss, rdf, atom)
    * @access private
    */
    private $_version;

    /**
     * Constructor
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $stream the stream to represent
     */
    public function __construct(array $stream)
    {
        $this->_stream = $stream;
        $this->_version = $this->_stream['channel']['version'];
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
    public function get($type='item')
    {
        $type = (string) $type;

        switch($type)
        {
            case 'ttl':
                // atom
                if('atom' === $this->_version) 
                    return (int) Config::iGet()->get('defaultStreamRefreshTime');
                // rss 2
                elseif(!empty($this->_stream['channel']['ttl']['contents']))
                {
                    $ttl = (int) $this->_stream['channel']['ttl']['contents'];

                    return (int) (Config::iGet()->get('defaultMinStreamRefreshTime') <= $ttl ? 
                        $ttl : Config::iGet()->get('defaultMinStreamRefreshTime'));
                }
                // rss 0.91
                elseif(!empty($this->_stream['channel']['skipDays']['contents']) || 
                    !empty($this->_stream['channel']['skipHours']['contents']))
                {
                    $skipDaysByName = array(
                        'Monday'=>0, 
                        'Tuesday'=>1, 
                        'Wednesday'=>2, 
                        'Thursday'=>3, 
                        'Friday'=>4, 
                        'Saturday'=>5, 
                        'Sunday'=>6
                    );
                
                    $date = new \DateTime();
                    $skipDays = $skipHours = array();
                
                    if(!empty($this->_stream['channel']['skipDays']['contents']))
                    {
                        foreach($this->_stream['channel']['skipDays']['contents'] as $d)
                        {
                            $d = (string) $d;
                            if(isset($skipDaysByName[$d]))
                                $skipDays[$d] = true;
                        }
                    }
                
                    if(!empty($this->_stream['channel']['skipHours']['contents']))
                    {
                        foreach($skipHour as $h)
                        {
                            $h = (int) $h;
                            if($h >=0 && $h < 24)
                                $skipHours[$h] = true;
                        }
                    }
                
                    if(empty($skipHours) && empty($skipDays))
                    {
                        $default = Config::iGet()->get('defaultStreamRefreshTime');
                        $date->modify('+'.$default.' minute'.(count($default) > 1 ? 's' : ''));
                    }
                    else
                    {
                        if(!empty($skipDays) && count($skipDays) < 7)
                        {
                            !isset($skipDays[$date->format('l')]) || $date->setTime(0, 1);
                
                            while(isset($skipDays[$date->format('l')]))
                                $date->modify('+1 day');
                
                            if(!empty($skipHours) && count($skipHours) < 24)
                            {
                                while(isset($skipHours[$date->format('G')]))
                                    $date->modify('+1 hour');
                            }
                        }
                        elseif(!empty($skipHours) && count($skipHours) < 24)
                        {
                            while(isset($skipHours[$date->format('G')]))
                                $date->modify('+1 hour');
                        }
                        else return (int) Config::iGet()->get('defaultStreamRefreshTime');
                    }
                
                    $dateinterval = $date->diff(new \DateTime());
                    unset($date);
                    $ttl = 0;
                    $ttl += ($dateinterval->d * 1440); // add days
                    $ttl += ($dateinterval->h * 60); // add hours
                    $ttl += $dateinterval->i; // add minutes

                    return (int) (Config::iGet()->get('defaultMinStreamRefreshTime') <= $ttl  ? 
                        $ttl : Config::iGet()->get('defaultMinStreamRefreshTime'));
                }
                elseif(!empty($this->_stream['channel']['updateFrequency']['contents']) ||
                    !empty($this->_stream['channel']['updatePeriod']['contents']))
                { // rss 1.0
                    $periodMinutes = array ( 
                        'hourly' => 60, // minutes in an hour
                        'daily' => 1440, // minutes in a day 
                        'weekly' => 10080, // minutes in a week
                        'monthly' => 43200, // minutes in a month
                        'yearly' => 525600, // minutes in a year
                    );

                    if(empty($this->_stream['channel']['updatePeriod']['contents']) || !isset($periodMinutes[$this->_stream['channel']['updatePeriod']['contents']]))
                        $this->_stream['channel']['updatePeriod']['contents'] = 'daily';
                    if(empty($this->_stream['channel']['updateFrequency']['contents']))
                        $this->_stream['channel']['updateFrequency']['contents'] = 1;
                    elseif(is_array($this->_stream['channel']['updateFrequency']['contents']))
                        $this->_stream['channel']['updateFrequency']['contents'] = (int) array_shift($this->_stream['channel']['updateFrequency']['contents']);

                    $ttl =  (int) $periodMinutes[$this->_stream['channel']['updatePeriod']['contents']] /
                            (int) $this->_stream['channel']['updateFrequency']['contents'];

                    return (int) (Config::iGet()->get('defaultMinStreamRefreshTime') <= $ttl  ? 
                        $ttl : Config::iGet()->get('defaultMinStreamRefreshTime'));
                }
                
                return (int) Config::iGet()->get('defaultStreamRefreshTime');
                break;

            case 'title':
                return (string) (empty($this->_stream['channel']['title']) ? 
                    'No title' : $this->_stream['channel']['title']['contents']);
                break;

            case 'item': // warning : we release memory here, each item is only available one time
                return empty($this->_stream['item']) ? 
                    false : new Item(array_shift($this->_stream['item']), $this->_version, $this);
                break;

            case 'author':
                if('atom' === $this->_version)
                {
                    $author = '';
                    if(!empty($this->_stream['channel']['author']['name']['contents']))
                    {
                        $author .= $this->_stream['channel']['author']['name']['contents'];
                    }
    
                    if(!empty($this->_stream['channel']['author']['email']['contents']))
                    {
                        $author .= empty($author) ? $this->_stream['channel']['author']['email']['contents'] : 
                            ', '.$this->_stream['channel']['author']['email']['contents'];
                    }
    
                    if(!empty($this->_stream['channel']['author']['uri']['contents']))
                    {
                        $author .= empty($author) ? $this->_stream['channel']['author']['uri']['contents'] : 
                            ', '.$this->_stream['channel']['author']['uri']['contents'];
                    }
    
                    if(!empty($author))
                        return $author;
                    elseif(!empty($this->_stream['channel']['author']['contents']))
                        return (string) $this->_stream['channel']['author']['contents'];
                    else return false;
                }
                else
                {
                    if(!empty($this->_stream['managingEditor']))
                        return (string) $this->_stream['managingEditor']['contents'];
                    elseif(!empty($this->_stream['webMaster']))
                        return (string) $this->_stream['webMaster']['contents'];
                    else return false;
                }
                break;

            case 'channel':
                return empty($this->_stream['channel']) ? false : $this->_stream['channel'];
                break;

            case 'version':
                return $this->_version;
                break;

            case 'realurl':
                if(!empty($this->_stream['channel']['link']))
                {
                    if(!empty($this->_stream['channel']['link']['contents']))
                        return $this->_stream['channel']['link']['contents'];
                    else
                    {
                        foreach($this->_stream['channel']['link'] as $link)
                        {
                            if(empty($link['attributes']) || empty($link['attributes']['href']) || 
                               empty($link['attributes']['type']) || 'text/html' !== $link['attributes']['type']) continue;

                            return $link['attributes']['href'];
                        }
                    }
                }
                elseif(!empty($this->_stream['channel']['source']))
                    return $this->_stream['channel']['source']['contents'];
                else return false; // ??
                break;

            case 'src': // warning : we release memory here, only available one time
                if(empty($this->_stream['src'])) return false;
                $src = $this->_stream['src'];
                unset($this->_stream['src']);
                return $src;
                break;

            default:
                return false;
                break;
        }
    }
    
    /**
     * Checks the stream is not empty
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return boolean true if not empty
     */
    public function hasItems()
    {
        return (bool) !empty($this->_stream['item']);
    }
}