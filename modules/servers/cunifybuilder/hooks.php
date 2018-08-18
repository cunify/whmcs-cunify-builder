<?php

add_hook('AfterModuleCreate', 1, function ($vars) {

    require_once __DIR__ . '/vendor/autoload.php';

    require_once __DIR__ . '/classes/Cpanel.php';
    require_once __DIR__ . '/classes/Whm.php';
    require_once __DIR__ . '/classes/CunifyFactory.php';
    require_once __DIR__ . '/classes/FtpNew.php';
    require_once __DIR__ . '/classes/Mirror.php';

    $cpanel = new Cpanel();

    $query = full_query("SELECT tblservers.*
    FROM tblservers
    WHERE tblservers.id=" . $vars['params']['serverid']);
    $server = mysql_fetch_array($query);

    $cpanel->server = $server;
    $cpanel->setupWebsite($vars['params']);

});
