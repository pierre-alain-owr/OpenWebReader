<?php
echo str_replace(array('; }', ';}'), '}', str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '),'', preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', file_get_contents('owr.css'))));
