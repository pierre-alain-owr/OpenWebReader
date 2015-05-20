<?php
/**
 * Object storing logs (errors or informations)
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
 * This object stores logs
 * @uses Singleton implements the singleton pattern
 * @uses Exception the exception handler
 * @package OWR
 */
class Logs extends Singleton
{
    /**
    * @var array the logs
    * @access private
    */
    protected $_logs = array();

    /**
     * Destructor
     * Writes the logs if not already done and if not empty
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     */
    public function __destruct()
    {
        $this->writeLogs(true);
    }

    /**
     * Stores log message in $this->_logs
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $msg the message to log
     * @param int $errcode the error code
     * @access public
     */
    public function log($msg, $errcode = Exception::E_OWR_WARNING)
    {
        $errcode = (int) $errcode;
        isset($this->_logs[$errcode]) || $this->_logs[$errcode] = array();

        if(is_array($msg))
        {
            foreach($msg as $m)
                $this->_logs[$errcode][] = (string) $m;
        }
        else $this->_logs[$errcode][] = (string) $msg;
    }

    /**
     * Returns all logged messages from $this->_logs and empty it
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return array the logs
     * @access public
     */
    public function getLogs()
    {
        $logs = (array) $this->_logs;
        $this->_logs = array();
        return $logs;
    }

    /**
     * Writes the logs
     * If in CLI mode, we will write the logs into HOME_PATH/logs/cli.log
     * else in default php error log file
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param boolean $write force writting of the logs into the log file
     * @return boolean true on success
     * @access public
     */
    public function writeLogs($write = false)
    {
        if(empty($this->_logs))
            return;

        $errstr = '';
        if(CLI)
        {
            foreach($this->_logs as $code=>$logs)
            {
                $errstr .= "[Log ".(int)$code."]\n".join("\n", $logs)."\n";
            }

            if(!empty($errstr)) error_log($errstr, 3, HOME_PATH.'logs'.DIRECTORY_SEPARATOR.'cli.log');
        }
        else
        {
            foreach($this->_logs as $code=>$logs)
            {
                $errstr .= "[Log ".(int)$code."]\n".join("\n", $logs)."\n";
            }

            if(!empty($errstr)) error_log($errstr, 0);
        }
        $this->_logs = array();
    }

    /**
     * Checks if we got logs
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return boolean true if logs are not empty
     * @access public
     */
    public function hasLogs()
    {
        return !empty($this->_logs);
    }

    /**
     * Displays CLI log file
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return string the content of CLI log file
     * @access public
     */
    public function getCLILogs()
    {
        $logPath = HOME_PATH . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'cli.log';
        return is_readable($logPath) ? file_get_contents($logPath) : '';
    }
}
