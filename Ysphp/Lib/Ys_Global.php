<?php 

    namespace Ysphp;

    Class Ys_Global{

        // public function __construct(){
        // }
        public static function array_find($ary, Callable $callback = null){
            
            if (!is_callable($callback) || $callback === null){
                throw new \Exception('Ysphp: array_find requires valid callback function as second parameter.');
            }
            $found = [];
            foreach ($ary as $key=>$a){
                if (call_user_func($callback,$a,$key)){
                    $found[] = $ary[$key];
                }
            }
            return $found;

        }

        public static function is_GET(){
            return $_SERVER['REQUEST_METHOD'] === 'GET' ? true:false;
        }

        public static function is_POST(){
            return $_SERVER['REQUEST_METHOD'] === 'POST' ? true:false;
        }

        public static function is_PUT(){
            return $_SERVER['REQUEST_METHOD'] === 'PUT' ? true:false;
        }

        public static function is_DELETE(){
            return $_SERVER['REQUEST_METHOD'] === 'DELETE' ? true:false;
        }

        public static function redirect($path){
            header( "Location: $path");
        }

        public static function selected($value, $compare){
            return $value === $compare ? 'selected':'';
        }

        public static function checked($value, $compare){
            return $value === $compare ? 'checked':'';
        }

        public static function actived($value){
            return strpos($_SERVER['REQUEST_URI'],$value) ? 'active':'';
        }

        public static function comparePrint($value,$compare,$print){
            return $value === $compare ? $print:'';
        }

        //curl requsts

        public static function post($args){
            $requestContent = array(
                CURLOPT_RETURNTRANSFER  =>  1,
                CURLOPT_URL             =>  $args['url'],
                CURLOPT_POST            =>  1,
                CURLOPT_POSTFIELDS      =>  $args['data'],
                CURLOPT_SSL_VERIFYPEER  =>  false,
                CURLOPT_SSL_VERIFYHOST  =>  false
            );

            if ( isset($args['headers']) ){
                $requestContent[CURLOPT_HTTPHEADER] = $args['headers'];
                $requestContent[CURLINFO_HEADER_OUT] = true;
            }
            

            return self::getCurlResponse($requestContent);
        }

        public static function get($args){
            $requestContent = array(
                CURLOPT_RETURNTRANSFER  =>  1,
                CURLOPT_URL             =>  is_array($args) ? $args['url'] . '?' . http_build_query($args['data']) : $args,
                CURLOPT_SSL_VERIFYPEER  =>  false,
                CURLOPT_SSL_VERIFYHOST  =>  false
            );

            if ( isset($args['headers']) ){
                $requestContent[CURLOPT_HTTPHEADER] = $args['headers'];
            }
            return self::getCurlResponse($requestContent);
        }

        public static function put($args){

            $requestContent = array(
                CURLOPT_RETURNTRANSFER  =>  1,
                CURLOPT_CUSTOMREQUEST   =>  'PUT',
                CURLOPT_URL             =>  $args['url'],
                CURLOPT_POSTFIELDS      =>  $args['data'],
                CURLOPT_SSL_VERIFYPEER  =>  2
            );

            if ( isset($args['headers']) ){
                $requestContent[CURLOPT_HTTPHEADER] = $args['headers'];
            }

            return self::getCurlResponse();
        }

        public static function delete($args){

            $requestContent = array(
                CURLOPT_RETURNTRANSFER  =>  1,
                CURLOPT_CUSTOMREQUEST   =>  'DELETE',
                CURLOPT_URL             =>  $args['url'],
                CURLOPT_POSTFIELDS      =>  $args['data'],
                CURLOPT_SSL_VERIFYPEER  =>  2
            );

            if ( isset($args['headers']) ){
                $requestContent[CURLOPT_HTTPHEADER] = $args['headers'];
            }

            return self::getCurlResponse();
        }

        private static function getCurlResponse($args){
            $curl = curl_init();
            curl_setopt_array($curl,$args);
            $response           = curl_exec($curl);
            $responseContent    = $response ? $response:array('hasError' => true, 'errorMessage' => curl_error($curl));
            curl_close($curl);
            return (object) $responseContent;
        }

    }

?>