<?php 

    spl_autoload_register(function($class_name){
        require dirname(__FILE__) . ( str_replace(array('\\','Ysphp'), array('/Lib/',''), $class_name) . '.php' ) ;
    });
    
?>