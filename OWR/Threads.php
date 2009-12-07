<?php
/**
 * Threads class
 * This class is used to manage PHP threads
 * It uses a priority queue to execute commands, limited in number of threads by config
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
 * This object is used to deal with objects instead of arrays
 * @package OWR
 * @uses Singleton extends the singleton pattern
 */
class Threads extends Singleton
{
    /**
     * @var mixed instance of \SplPriorityQueue
     * @access protected
     */
    protected $_queue;

    /**
     * @var int maximum threads to launch simultaneously
     * @access protected
     */
    protected $_max;

    /**
     * @var string the path to PHP executable and to cli.php
     * @access protected
     */
    protected $_cmd;

    /**
     * Constructor
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    protected function __construct()
    {
        $this->_queue = new \SplPriorityQueue();
        $this->_max = Config::iGet()->get('maxThreads');
        $this->_cmd = ((string) Config::iGet()->get('phpbin')).' '.HOME_PATH.'cli.php ';
    }

    /**
     * Destructor
     * Waits for the execution of all commands in queue
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     */
    public function __destruct()
    {
        $i = 0;
        while($this->_queue->count())
        {
            if($this->_getProcessCount() < $this->_max)
            {
                $this->_exec();
            }
            else
            { // have to wait a bit
                sleep(++$i);
            }
        }
    }

    /**
     * Adds a command to the queue
     * Executes immediatly the command if any slot left
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param array $args the arguments (do, id, etc..)
     * @param int $priority priority level, from 1 to 3
     */
    public function add(array $args, $priority = 2)
    {
        $priority = (int) $priority;
        if($priority > 3 || $priority < 1)
            $priority = 2;

        $cmd = ((string) Config::iGet()->get('nicecmd')).$this->_cmd;
        foreach($args as $k=>$v)
        {
            $cmd .= escapeshellarg($k).'='.escapeshellarg($v).' ';
        }

        $cmd .= ' >> '.HOME_PATH.'logs/cli.log'; // redirect outputs to logs and set to non-blocking

        $this->_queue->insert($cmd, $priority);
        if($this->_getProcessCount() < $this->_max)
        {
            $this->_exec();
        }
    }

    /**
     * Executes the command
     *
     * @access protected
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return mixed return int status of executed command, or false if nothing left in queue
     */
    protected function _exec()
    {
        $cmd = $this->_queue->extract();
        if(!$cmd) return false;

        return (int) pclose(popen(escapeshellcmd($cmd).' &', 'r'));
    }

    /**
     * Returns the number of threads that we have already launched
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return int number of php threads for us
     */
    protected function _getProcessCount()
    {
        exec(sprintf((string) Config::iGet()->get('grepcmd'), escapeshellcmd($this->_cmd)), $lines);
        return count($lines);
    }
}