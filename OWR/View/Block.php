<?php
/**
 * Represents a block to be displayed in the layout of the view
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
 * @subpackage View
 */
namespace OWR\View;
use OWR\Exception;
/**
 * This object represents a block inside the layout of the view
 * @uses Singleton implements the singleton pattern
 * @uses Cache check dwoo cache directories
 * @uses User the current user
 * @uses OWR\Config get the cachetime
 * @uses OWR\Strings multi-byte wordwrap for abstract
 * @package OWR
 * @subpackage View
 */
class Block
{
    /**
     * @var string type of block
     * @access protected
     */
    protected $_type;

    /**
     * @var string datas to store
     * @access protected
     */
    protected $_datas;

    /**
     * Constructor
     * Sets the type (default to html) and datas
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @param string $datas the datas to store
     * @param string $type the type of block, default to html
     */
    public function __construct($datas, $type = 'html')
    {
        $type = (string) $type;

        switch($type)
        {
            case 'css':
            case 'css_print':
            case 'css_screen':
            case 'css_inline':
            case 'html':
            case 'js':
            case 'js_inline':
                $this->_type = $type;
                break;

            default:
                throw new Exception('Type "'.$type.'" unknown');
                break;
        }

        $this->_datas = (string) $datas;
    }

    /**
     * Returns the datas with their tags if any
     *
     * @access public
     * @author Pierre-Alain Mignot <contact@openwebreader.org>
     * @return string the content of the block
     */
    public function __toString()
    {
        switch($this->_type)
        {
            case 'css_inline':
                return '<style type="text/css">'.$this->_datas.'</style>';

            case 'css':
                $media = 'all';
            case 'css_print':
                isset($media) || $media = 'print';
            case 'css_screen':
                isset($media) || $media = 'screen';
                return '<link rel="stylesheet" type="text/css" media="'.$media.'" href="'.$this->_datas.'"/>';

            case 'js':
                return '<script type="text/javascript" src="'.$this->_datas.'"></script>';

            case 'js_inline':
                return '<script type="text/javascript">'.$this->_datas.'</script>';

            case 'html':
            default:
                return (string) $this->_datas;
        }
    }
}