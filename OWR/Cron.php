<?php
/**
 * Cron base class
 * This class is used to add/remove/modify a cron command (Linux only)
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
use OWR\Stream\Parser as StreamParser;
/**
 * This object manages cron job adding/deleting/locking
 * @uses DB the database link
 * @uses SQLResult a result from database
 * @uses User the current user
 * @uses Controller get temporary filename and request begin time
 * @uses Exception the exceptions handler
 * @uses cURLWrapper get the HTTP queries time
 * @uses Singleton implements the singleton pattern
 * @package OWR
 */
class Cron extends Singleton
{
    /**
    * @var string begin line of crontab
    */
    const CRON_START = "# FOLLOWING LINES COME FROM OPENWEBREADER - PLEASE DO NOT EDIT, AUTOMATIC #";

    /**
    * @var string end line of crontab
    * @access protected
    */
    const CRON_STOP = "# END OPENWEBREADER CRONTAB #";

    /**
    * @var boolean is a cron job locked ?
    * @access protected
    */
    protected $_isLocked = false;

    /**
    * @var string url of the current installation
    * @access protected
    */
    protected $_url;

    /**
    * @var string name of the current action
    * @access protected
     */
    protected $_currentAction;

    /**
    * @var int minimum ttl found in database
    * @access protected
     */
    protected $_minCronTtl;

    /**
    * @var array the new crontab lines
    * @access protected
     */
    protected $_cronTab;

    /**
    * @var boolean has the crontab changed ?
    * @access protected
     */
    protected $_hasChanged = false;

    /**
     * Constructor
     * 
     * @access public
     */
    protected function __construct()
    {
        $this->_url = Config::iGet()->get('surl');
        if(@filesize(HOME_PATH.'logs'.DIRECTORY_SEPARATOR.'cli.log') > Config::iGet()->get('maxLogFileSize'))
        {
            @unlink(HOME_PATH.'logs'.DIRECTORY_SEPARATOR.'cli.log');
            @touch(HOME_PATH.'logs'.DIRECTORY_SEPARATOR.'cli.log');
        }

        try
        {
            $minCronTll = $this->getMinTtl();
        }
        catch(Exception $e)
        {
            throw new Exception($e->getContent(), Exception::E_OWR_WARNING);
        }
        $this->_minCronTtl =  $minCronTll ?: 0;
    }

    /**
     * Destructor
     * It will unlink the locking file for the current action if not already done (strange but if errors..)
     * and writes the new crontab if any
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    public function __destruct()
    {
        if($this->_currentAction)
        {
            @unlink(HOME_PATH.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'.cron_'.$this->_currentAction);
        }

        $this->_write();
    }

    /**
     * "touch" a file into the cache directory
     * This function is used to lock a cron action, preventing multiple jobs doing the same action
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $action the name of the action to lock
     * @return boolean true on success
     */
    public function lock($action)
    {
        Logs::iGet()->log(date('Y/m/d H:i:s')." Executing CronJob: '".$action."'", 200);
        $this->_currentAction = $action;
        return $this->isLocked($action) ? true :
            touch(HOME_PATH.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'.cron_'.$this->_currentAction);
    }

    /**
     * Deletes a file from the cache directory
     * This function is used to unlock a cron action
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $action the name of the action to unlock
     * @param boolean $error has the end of the script been triggered by an error ?
     * @return boolean true on success
     */
    public function unlock($action, $error = false)
    {
        Logs::iGet()->log(date('Y/m/d H:i:s')." End CronJob".($error ? ' (error)' : '').
                            ": '".$action."'", $error ? 500 : 200);
        $action = $action;
        $this->_currentAction !== $action || $this->_currentAction = ''; // reset if current
        return $this->isLocked($action) ? unlink(HOME_PATH.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'.cron_'.$action) : true;
    }

    /**
     * Checks that the action is not locked
     * This function checks that the locking file exists
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $action the name of the action to check
     * @return boolean true if locked
     */
    public function isLocked($action)
    {
        return file_exists(HOME_PATH.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'.cron_'.$action);
    }

    /**
     * Gets the minimum time to live from streams
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return int ttl
     */
    public function getMinTtl()
    {
        $minCronTll = DB::iGet()->getOne('
    SELECT MIN(ttl) AS ttl
        FROM streams');
        return $minCronTll->next() ? $minCronTll->ttl : 0;
    }

    /**
     * Logs the end of the script execution
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $action the name of the action aborted
     */
    public function abort($action)
    {
        Logs::iGet()->log(date('Y/m/d H:i:s')." Aborted CronJob '".$action."'", Exception::E_OWR_UNAVAILABLE);
    }

    /**
     * Manages Cron adding/removing
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param mixed $args the arguments
     * @access public
     */
    public function manage($args)
    {
        if(is_array($args))
        {
            if(!isset($args['type']))
            {
                throw new Exception('Missing type for cron managing', Exception::E_OWR_WARNING);
                return false;
            }

            switch($args['type'])
            {
                case 'refreshstream':
                    if(!isset($args['ttl']))
                    {
                        throw new Exception('Missing ttl for cron managing:refreshstream', Exception::E_OWR_WARNING);
                    }

                    if($this->_minCronTtl > 0 && $this->_minCronTtl <= $args['ttl']) return;

                    if($args['ttl'] > 60)
                    {
                        $hours = round($args['ttl'] / 60);
                        
                        // at least we update every day at 1am
                        if($hours >= 24)
                        {
                            $hour = '1';
                            $minute = '*';
                            $monthDay = '*';
                            $weekDay = '*';
                            $month = '*';
                        }
                        else
                        {
                            $minute = '*';
                            $hour = '*/'.$hours;
                            $monthDay = '*';
                            $weekDay = '*';
                            $month = '*';
                        }
                    }
                    else
                    {
                        $hour = '*';
                        $minute = '*/'.$args['ttl'];
                        $monthDay = '*';
                        $weekDay = '*';
                        $month = '*';
                    }
                    $comment = 'OpenWebReader Common Stream Update';
                    break;

                case 'managefavicons':
                    // manage favicons every days at 1am
                    $comment = 'OpenWebReader Cronjob for streams favicon adding/cleaning';
                    $hour = '1';
                    $minute = '1';
                    $monthDay = '*';
                    $weekDay = '*';
                    $month = '*';
                    break;

                case 'checkstreamsavailability':
                    // check for dead streams every hours
                    $comment = 'OpenWebReader Cronjob for dead streams checking';
                    $hour = '*';
                    $minute = '1';
                    $monthDay = '*';
                    $weekDay = '*';
                    $month = '*';
                    break;

                default:
                    throw new Exception('Invalid type for cron managing, aborting', Exception::E_OWR_WARNING);
                    break;
            }
            
            $cmd = Config::iGet()->get('phpbin').' '.HOME_PATH.'cli.php \'do\'='.escapeshellarg(escapeshellcmd($args['type'])).' >> '.HOME_PATH.'logs/cli.log';
            
            $this->_add($args['type'], $hour, $minute, $monthDay, 
                            $weekDay, $month, $cmd, $comment);
        }
        else
        {
            $this->_delete($args);
        }
    }

    /**
     * Writes the new crontab
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     */
    protected function _write()
    {
        if(!$this->_hasChanged || empty($this->_cronTab)) return false;

        $filename = Cache::getRandomFilename(true);
        
        if(false === file_put_contents($filename, join("\n", $this->_cronTab)))
        {
            throw new Exception('No way to write into cache directory');
        }

        exec('crontab '.$filename);
        
        $this->_hasChanged = false;

        return @unlink($filename);
    }

    /**
     * Adds/Edits a cron job
     * This function has been adapted from http://matthieu.developpez.com/execution_periodique, thanks to the author
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $type name of the action
     * @param int $hour
     * @param int $minute
     * @param int $monthDay
     * @param int $month
     * @param string $cmd the command to execute
     * @param string $comment a comment for this cron job
     * @link http://en.wikipedia.org/wiki/Crontab for uncommented parameters
     * @return string $type
     */
    protected function _add($type, $hour, $minute, $monthDay, $weekDay, $month, $cmd, $comment)
    {
        $type = (string) $type;
        $type = $this->_url .':'. $type;
        $oldCrontab = array();
        $newCrontab = array();
        $isSection = $done = $escape = false;
        isset($this->_cronTab) || exec('crontab -l', $this->_cronTab);

        foreach($this->_cronTab as $index => $line)
        {
            if($escape === true)
            {
                $escape = false;
                continue;
            }
            
            $line = (string) $line;

            if($isSection === true)
            {
                $wordsLine = explode(' ', $line);
                if ('#' === (string)$wordsLine[0] && $type === (string)$wordsLine[1])
                {
                    $newCrontab[] = '# '.$type.' : '.$comment;
                    $newCrontab[] = $minute.' '.$hour.' '.$monthDay.' '.$month.' '.$weekDay.' '.$cmd;
                    $done = true;
                    $escape = true;
                    continue;
                }
            }
            
            if($line === self::CRON_START) 
            {
                $isSection = true;

                if(isset($this->_cronTab[$index + 1]) && ((string)$this->_cronTab[$index + 1] === self::CRON_STOP))
                {
                    $newCrontab[] = self::CRON_START;
                    $newCrontab[] = '# '.$type.' : '.$comment;
                    $newCrontab[] = $minute.' '.$hour.' '.$monthDay.' '.$month.' '.$weekDay.' '.$cmd;
                    $newCrontab[] = self::CRON_STOP;
                    break;
                }
            }
            
            if($line === self::CRON_STOP)
            {
                if(!$done) 
                {
                    $newCrontab[] = '# '.$type.' : '.$comment;
                    $newCrontab[] = $minute.' '.$hour.' '.$monthDay.' '.$month.' '.$weekDay.' '.$cmd;
                }
            }
            
            $newCrontab[] = $line;
        }
        
        if($isSection === false)
        {
            $newCrontab[] = self::CRON_START;
            $newCrontab[] = '# '.$type.' : '.$comment;
    
            $newCrontab[] = $minute.' '.$hour.' '.$monthDay.' '.$month.' '.$weekDay.' '.$cmd;
            $newCrontab[] = self::CRON_STOP;
        }

        if($this->_cronTab !== $newCrontab)
        {
            $this->_hasChanged = true;
            $this->_cronTab = $newCrontab;
        }

        return $type;
    }

    /**
     * Deletes a cron job
     * This function has been adapted from http://matthieu.developpez.com/execution_periodique, thanks to the author
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $type name of the action
     * @return string $type
     */
    protected function _delete($type)
    {
        $oldCrontab = array();
        $newCrontab = array();
        $isSection = $escape = false;
        
        $type = (string)$type;
        $type = $this->_url .':'. $type;

        isset($this->_cronTab) || exec('crontab -l', $this->_cronTab);
        
        foreach($this->_cronTab as $line)
        {
            if($isSection === true)
            {
                $wordsLine = explode(' ', $line);
                if($escape) $escape = false;
                elseif('#' !== (string)$wordsLine[0] || $type !== (string)$wordsLine[1])
                {
                    $newCrontab[] = $line;
                }
                else $escape = true;
            }
            else
            {
                $newCrontab[] = $line;
            }
            
            if ((string)$line === self::CRON_START) { $isSection = true; }
        }

        if($this->_cronTab !== $newCrontab)
        {
            $this->_hasChanged = true;
            $this->_cronTab = $newCrontab;
        }
        
        return $type;
    }
}
