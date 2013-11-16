<?php
namespace OWR\View\Block;

use OWR\Exception as Exception;

class Datas extends Block
{
    public function __construct($datas, $type = 'datas')
    {
        $type = (string) $type;

        switch($type)
        {
            case 'datas':
                $this->_datas = $datas;
                $this->_type = $type;
                break;

            case 'css':
            case 'css_print':
            case 'css_screen':
            case 'css_inline':
            case 'html':
            case 'js':
            case 'js_inline':
                parent::__construct($datas, $type);
                break;

            default:
                throw new Exception('Type "'.$type.'" unknown');
                break;
        }
    }

    public function getDatas()
    {
        return $this->_datas;
    }
}