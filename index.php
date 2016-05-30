<?php
    if(version_compare(PHP_VERSION, '5.4.0', '<')) {
        echo 'Vous devez avoir au minimum la version 5.4.0 de PHP !';
    }

    require 'core/core.php';
    $core = new core;
    spl_autoload_register('core::autoload');
    $core -> router();
?>