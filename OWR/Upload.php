<?php
/**
 * Upload class
 * This class tries to deal with uploaded files
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
 * This object manages checking/moving uploaded files
 * @uses Cache get an unique file name
 * @package OWR
 */
class Upload
{
    /**
     * @var string name of the uploaded file(s)
     * @access protected
     */
    protected $_name;

    /**
     * @var array associative array of values used to check uploaded file integrity
     * @access protected
     */
    protected $_args;

    /**
     * Constructor
     * Sets $this->_name && $this->_isArray
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @param string $name the name of the uploaded file
     * @param array $args associative array of values used to check uploaded file integrity
     */
    public function __construct($name, array $args)
    {
        $this->_name = (string) $name;
        $this->_args = $args;
    }

    /**
     * Returns the path to uploaded file(s) after having checked/moved it
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access public
     * @return mixed string or array of path to uploaded file(s)
     */
    public function get()
    {
        if(!isset($_FILES[$this->_name]))
        {
            throw new Exception('No file "'.$this->_name.'" uploaded', Exception::E_OWR_WARNING);
        }

        $file =& $_FILES[$this->_name];

        if(is_array($file['tmp_name']))
        { // multiple file uploads
            if(!isset($this->_args['isArray']) || !$this->_args['isArray'])
            {
                throw new Exception('I don\'t accept array', Exception::E_OWR_WARNING);
            }

            $files = array();

            $nb = count($file['tmp_name']);
            for($i=0;$i<$nb;$i++)
            {
                $f = array(
                    'tmp_name'  => $file['tmp_name'][$i],
                    'error'     => $file['error'][$i],
                    'name'      => $file['name'][$i],
                    'size'      => $file['size'][$i],
                    'type'      => $file['type'][$i],
                );

                try
                {
                    $files[] = $this->_move($f);
                }
                catch(Exception $e)
                {
                    Logs::iGet()->log($e->getContent(), $e->getCode());
                }
            }

            if(empty($files))
            {
                throw new Exception('No way to get at least one uploaded file', Exception::E_OWR_WARNING);
            }

            return $files;
        }
        else
        {
            return $this->_move($file);
        }
    }

    /**
     * Tries to move the uploaded file if no errors
     *
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @access protected
     * @param array $file the file to move
     * @return string the path to the moved file on success
     */
    protected function _move(array $file)
    {
        if($file['error'] > 0)
        {
            switch($file['error'])
            {
                case UPLOAD_ERR_NO_FILE: throw new Exception('Missing file.', Exception::E_OWR_WARNING); break;
                case UPLOAD_ERR_INI_SIZE: throw new Exception('Filesize is more than limit configuration.', Exception::E_OWR_WARNING); break;
                case UPLOAD_ERR_FORM_SIZE: throw new Exception('Filesize is more than limit configuration.', Exception::E_OWR_WARNING); break;
                case UPLOAD_ERR_PARTIAL: throw new Exception('Error while transfering, try again.', Exception::E_OWR_WARNING); break;
                default: throw new Exception('An error occured, try again.', Exception::E_OWR_WARNING); break;
            }
        }
        
        if ($file['size'] > $this->_args['maxFileSize'])
        {
            throw new Exception('Filesize is more than limit configuration.', Exception::E_OWR_WARNING);
        }
        
        if(!is_uploaded_file($file['tmp_name']))
        {
            throw new Exception('Incorrect file name.', Exception::E_OWR_WARNING);
        }

        
        if($this->_args['mime'] !== $file['type'])
        {
            throw new Exception('Incorrect file type.', Exception::E_OWR_WARNING);
        }

        if(class_exists('finfo', false))
        {
            $finfo = new \finfo(FILEINFO_SYMLINK | FILEINFO_MIME);
            if(!$finfo) 
            {
                throw new Exception('Can not open fileinfo', Exception::E_OWR_WARNING);
            }
            
            if(0 !== mb_strpos($finfo->file($file['tmp_name']), $this->_args['finfo_mime'], 0, 'UTF-8'))
            {
                throw new Exception('Incorrect file type.', Exception::E_OWR_WARNING);
            }
            unset($finfo);
        }

        if($this->_args['ext'] !== mb_strtolower(mb_substr($file['name'], - mb_strlen($this->_args['ext'], 'UTF-8'), mb_strlen($this->_args['ext'], 'UTF-8'), 'UTF-8'), 'UTF-8'))
        {
            throw new Exception('Incorrect file name.', Exception::E_OWR_WARNING);
        }
        
        $path = Cache::getRandomFilename(true); // uploaded file goes to tmp :-)
        
        if(!move_uploaded_file($file['tmp_name'], $path))
        {
            throw new Exception('Error while moving uploaded file, please try again.', Exception::E_OWR_WARNING);
        }

        return $path;
    }
}