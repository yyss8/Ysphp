<?php 

    namespace Ysphp\Lib;

    Class Main{

        public function __construct(){

        }

        public function array_find($ary, Callable $callback = null){
            
            if (!is_callable($callback) || $callback === null){
                throw new \Exception("Ysphp: array_find requires valid callback function as second parameter.");
            }
            $found = [];
            foreach ($ary as $key=>$a){
                if (call_user_func($callback,$a,$key)){
                    $found[] = $ary[$key];
                }
            }
            return $found;

        }

        public function is_GET(){
            return $_SERVER['REQUEST_METHOD'] === "GET" ? true:false;
        }

        public function is_POST(){
            return $_SERVER['REQUEST_METHOD'] === "POST" ? true:false;
        }

        public function redirect($path){
            header( "Location: $path");
        }

        public function selected($value, $compare){
            return $value === $compare ? "selected":"";
        }

        public function checked($value, $compare){
            return $value === $compare ? "checked":"";
        }

        public function actived($value){
            return strpos($_SERVER['REQUEST_URI'],$value) ? "active":"";
        }

        public function comparePrint($value,$compare,$print){
            return $value === $compare ? $print:"";
        }

    }

?>