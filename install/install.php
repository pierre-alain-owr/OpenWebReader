<?php
namespace OWR;
die('COMMENT THIS LINE TO START INSTALL PROCESS');

define('PATH', dirname(__DIR__).DIRECTORY_SEPARATOR); // define root path
define('HOME_PATH', PATH.'OWR'.DIRECTORY_SEPARATOR); // define home path

$libraries = array('curl' => false, 'imagick' => false, 'pdo' => true, 'mbstring'=>true, 'xmlreader'=>true, 'pdo_mysql'=>true, 'pcre'=>true, 'json' => true, 'libxml' => true, 'gettext' => true, 'date'=>true,'intl'=>true, 'spl' => true, 'filter' => true); // 'true' is required, but others are really needed :)

$messages = array();
$messages['PHP extensions'] = array();

$errors = array();

if(isset($_GET['step']))
    $step = (int) $_GET['step'];
else $step = 0;

if(isset($_GET['valid']))
    $valid = (bool) $_GET['valid'];
else $valid = false;

foreach($libraries as $library => $required)
{
    $messages['PHP extensions'][$library] = extension_loaded($library);
    if(!$messages['PHP extensions'][$library] && $required)
    {
        $errors[] = 'Missing required library '.$library;
    }
}

$messages['PHP extensions']['finfo'] = class_exists('finfo',false);

$messages['PHP_VERSION'] = version_compare(PHP_VERSION, '5.3.0', '>=');
if(!$messages['PHP_VERSION']) 
{
    $errors[] = 'Sorry but OpenWebReader required at least PHP version 5.3';
}

$messages['OS_VERSION'] = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'Windows' : PHP_OS;
if('Windows' === $messages['OS_VERSION'])
{
    $errors[] = 'WARNING : OpenWebReader has not been tested to run under Windows. You should not install it on production server.';
}

if($step)
{
    switch($step)
    {
        case 1:
            if(!file_exists('../OWR/cfg.php')) $errors[] = 'Please reload the page after having set the config file (and edited values !)';
            break;
        case 2:
            if(!file_exists('../OWR/cfg.php')) $errors[] = 'Please reload the page after having set the config file (and edited values !)';
            elseif(!is_writeable('../OWR/cache/')) $errors[] = 'Please chmod the directory ../OWR/cache/ to be accessible in read/write for web server (example : chown -R www-data ../OWR/cache/ && chmod -R 700 ../OWR/cache/)';
            elseif(!is_writeable('../OWR/logs/')) $errors[] = 'Please chmod the directory ../OWR/logs/ to be accessible in read/write for web server (example : chown -R www-data ../OWR/logs/ && chmod -R 700 ../OWR/logs/)';
            elseif(!is_writeable('../OWR/logs/cli.log')) $errors[] = 'Please chmod the file ../OWR/logs/cli.log to be accessible in read/write for web server (example : chown -R www-data ../OWR/logs/ && chmod 700 ../OWR/logs/ && chmod 600 ../OWR/logs/cli.log)';
            break;
        case 3:
            if(!file_exists('../OWR/cfg.php')) $errors[] = 'Please reload the page after having set the config file (and edited values !)';
            elseif(!is_writeable('../OWR/cache/')) $errors[] = 'Please chmod the directory ../OWR/cache/ to be accessible in read/write for web server (example : chown -R www-data ../OWR/cache/ && chmod -R 700 ../OWR/cache/)';
            elseif(!is_writeable('../OWR/logs/')) $errors[] = 'Please chmod the directory ../OWR/logs/ to be accessible in read/write for web server (example : chown -R www-data ../OWR/logs/ && chmod -R 700 ../OWR/logs/)';
            elseif(!is_writeable('../OWR/logs/cli.log')) $errors[] = 'Please chmod the file ../OWR/logs/cli.log to be accessible in read/write for web server (example : chown -R www-data ../OWR/logs/ && chmod -R 700 ../OWR/logs/ && chmod 600 ../OWR/logs/cli.log)';
            else
            {
                include '../OWR/cfg.php';
                try
                {
                    DB::iGet();
                }
                catch(Exception $e)
                {
                    $errors[] = $e->getContent();
                }
            }
            break;
        case 4:
            if(!file_exists('../OWR/cfg.php')) $errors[] = 'Please reload the page after having set the config file (and edited values !)';
            else
            {
                include '../OWR/cfg.php';
                try
                {
                    $db = DB::iGet();
                }
                catch(Exception $e)
                {
                    $errors[] = $e->getContent();
                    break;
                }
            }
            
            $sql = @file_get_contents('./init_db.sql');
            if(!$sql)
            {
                $errors[] = 'Can not get the contents of the file init_db.sql, please check your package and/or rights on this file (read access required for web server)';
                break;
            }
            if(!$valid) break;
            $queries = explode(";", $sql);
            $db->beginTransaction();
            foreach($queries as $query)
            {
                if(!trim($query)) continue;
                try
                {
                    $db->set($query);
                }
                catch(Exception $e)
                {
                    $db->rollback();
                    $errors[] = $e->getContent();
                    break;
                }
            }
            $db->commit();
            break;
        default:$step = 0; break;
    }
}

?>

<html>
    <head>
        <title>OpenWebReader Installation</title>
        <style type="text/css">
            body {
                font-family:sans-serif;
            }
        </style>
    </head>
    <body>
    <h1>Welcome to OpenWebReader installation process</h1>
    <h3>List of steps :</h3>
    <ul>
        <li style="color:red;">ALWAYS check for errors at the bottom of the pages and try to repair them.</li>
        <li>
<?php if (0 === $step) echo '<strong><em>'; ?>
            Edit the file ./conf-dist.php with your configuration and copy it to ../OWR/cfg.php. <a href="./install.php?step=1">GO</a>
<?php if (0 === $step) echo '</em></strong>'; ?>
        </li>
        <li>
<?php if (1 === $step) echo '<strong><em>'; ?>
            Make the directories "../OWR/cache/" and "../OWR/logs/" accessibles in read/write for web server (example : chown -R www-data ../OWR/cache/ && chmod -R 700 ../OWR/cache/ && chown -R www-data ../OWR/logs/ && chmod -R 700 ../OWR/logs/). <a href="./install.php?step=2">GO</a>
<?php if (1 === $step) echo '</em></strong>'; ?>
        </li>
        <li>
<?php if (2 === $step) echo '<strong><em>'; ?>
            Test database connexion and utf8 support. You will need to have already create the database with UTF-8 charset (example: "CREATE DATABASE openwebreader DEFAULT CHARACTER SET utf8"). At the moment, only MySQL is supported. <a href="./install.php?step=3">GO</a>
<?php if (2 === $step) echo '</em></strong>'; ?>
        </li>
        <li>
<?php if (3 === $step || (4 === $step && !$valid)) echo '<strong><em>'; ?>
            Install database. 
<?php if(4 === $step && !$valid) {?>
            WARNING IT WILL ERASE EXISTING TABLES, please <a href="./install.php?step=4&amp;valid=1">validate</a>
<?php } else {?>
            <a href="./install.php?step=4">GO</a>
<?php }
if (3 === $step || (4 === $step && !$valid)) echo '</em></strong>'; ?>
        </li>
        <li>
<?php if (4 === $step && $valid) echo '<strong><em>'; ?>
            Delete this directory (or rename this script to something.not_php : important if you don't want to have bad problems later)
<?php if (4 === $step && $valid) echo '</em></strong>'; ?>
        </li>
    </ul>
    <h3>Server checks :</h3>
    <ul>
<?php
    foreach($messages as $message => $ok)
    {
        if(!is_array($ok))
            echo '<li'.(!$ok ? ' style="color:red;font-weight:bold;"' : ' style="color:green;"').'>'.$message.'</li>';
        else
        {
            echo '<li><strong>'.$message.' :</strong>';
            echo '<ul>';
            foreach($ok as $m=>$v)
            {
                echo '<li'.(!$v ? ' style="color:red;font-weight:bold;"' : ' style="color:green;"').'>'.$m.'</li>';
            }
            echo '</ul></li>';
        }
    }
?>
    </ul>
    <h3>Status :</h3>
<?php
    if(!empty($errors))
    {
?>
        <h4>Errors :</h4>
        <ul>
<?php
        foreach($errors as $errors)
        {
            echo '<li style="color:red;font-weight:bold;">'.$errors.'</li>';
        }
        echo '<li><a href="./install.php?step='.$step.'">retry</a></li>';
?>
        </ul>
<?php
    }
    elseif($step === 4)
    {
        if(!$valid)
        {
            echo '<p style="color:green;">Please <a href="./install.php?step=4&amp;valid=1">validate</a> erasing of existing tables</p>';
        }
        else echo '<p style="color:green;">Installation finished ! You can now delete this directory and <a href="../index.php?do=edituser">register an administrator</a></p>';
    }
    else
    {
        echo '<p style="color:green;">Cool ! No fatal errors. You can go next <a href="./install.php?step='.($step + 1).'">step</a></p>';
    }
?>
    </body>
</html>
