<?php 

    spl_autoload_register(function($class_name){
        $path = dirname(__FILE__) . ( str_replace(array('\\','Ysphp'), array('/Lib/',''), $class_name) . '.php' ) ;
        if ( file_exists( $path ) ){
            require $path;
        }
    });
    
?>
