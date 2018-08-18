<?php


add_hook('AfterModuleCreate', 1, function ($vars) {
    
   require_once __DIR__ . '/vendor/autoload.php';
    
    $cpanel = new Cpanel();

    $cpanel->setupWebsite($vars);

});
