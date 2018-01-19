<?php 

    namespace Ysphp;

    class Builder{

        public static function loadCSS($csses){
            foreach ($csses as $css){
                echo '<link rel="stylesheet" href="'. $css .'" type="text/css" >';
            }
        }

        public static function loadScripts($scripts){
            foreach ($scripts as $script){
                echo '<script src="'. $script .'"></script>';
            }
        }

        public static function printJsObject($name, $ary = []){
            $str = "var $name = {%s}";
            $keyValues = [];
            foreach ($ary as $key=>$a){
                $s = is_string($a) ? $a:json_encode($a,JSON_UNESCAPED_UNICODE);
                $keyValues[] = "$key:'$s'";
            }
            echo sprintf($str, implode(',',$keyValues));
        }

        public static function cn_json_encode($ary){
            return json_encode($ary,JSON_UNESCAPED_UNICODE);
        }

        public static function rl_json_encode($ary){
            return urldecode(json_encode(url_encode($ary)));
        }

    }
?>