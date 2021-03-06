<?php 

    namespace Ysphp;

    Class Utilities{

        public static function get_request_ip(){

            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }

            return $ip;
        }

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

            if ( isset( $args['data'] ) ){
                $data = isset( $args['json'] ) && $args['json'] === true ? http_build_query( $args['data'] ):$args['data'];
            }else{
                $data = '';
            }

            $requestContent = [
                CURLOPT_RETURNTRANSFER  =>  1,
                CURLOPT_URL             =>  $args['url'],
                CURLOPT_POST            =>  1,
                CURLOPT_POSTFIELDS      =>  $data,
                CURLOPT_SSL_VERIFYPEER  =>  false,
                CURLOPT_SSL_VERIFYHOST  =>  false
            ];

            return isset( $args['send'] ) && $args['send'] === false
                   ? self::prepareCurlObj( $requestContent, $args )
                   : self::getCurlResponse( self::prepareCurlObj( $requestContent, $args ) );
        }

        public static function multiCurl( $objArgs ){

            $mt         = curl_multi_init();
            $jobs       = [];
            $responses  = [];

            foreach ( $objArgs as $index => $objArg ){
                $cl = curl_init();
                curl_setopt_array( $cl, $objArg['args']);
                $jobs[$index] = $cl;
                curl_multi_add_handle( $mt, $cl);
            }

            do {
                curl_multi_exec($mt, $exec);
            } while($exec > 0);

            foreach ( $objArgs as $index => $args ){
                $response = curl_multi_getcontent( $jobs[$index] );

                $responses[] = (object)[
                    'response'      =>  $response,
                    'index'         =>  $index
                ];

                curl_multi_remove_handle( $mt,  $jobs[$index] );
                curl_close( $jobs[$index] );
            }

            curl_multi_close( $mt );

            return $responses;
        }

        public static function get($args){

            $data = isset( $args['data'] ) ? '?' . http_build_query($args['data']):'';

            $requestContent = array(
                CURLOPT_RETURNTRANSFER  =>  1,
                CURLOPT_URL             =>  is_array($args) ? $args['url'] . $data : $args,
                CURLOPT_SSL_VERIFYPEER  =>  false,
                CURLOPT_SSL_VERIFYHOST  =>  false
            );

            return isset( $args['send'] ) && $args['send'] === false
                   ? self::prepareCurlObj( $requestContent, $args )
                   : self::getCurlResponse( self::prepareCurlObj( $requestContent, $args ) );
        }

        public static function put($args){

            if ( isset( $args['data'] ) ){
                $data = isset( $args['json'] ) && $args['json'] === true ? http_build_query( $args['data'] ):$args['data'];
            }else{
                $data = '';
            }

            $requestContent = array(
                CURLOPT_RETURNTRANSFER  =>  1,
                CURLOPT_CUSTOMREQUEST   =>  'PUT',
                CURLOPT_URL             =>  $args['url'],
                CURLOPT_POSTFIELDS      =>  $data,
                CURLOPT_SSL_VERIFYPEER  =>  2
            );

            return isset( $args['send'] ) && $args['send'] === false
                   ? self::prepareCurlObj( $requestContent, $args )
                   : self::getCurlResponse( self::prepareCurlObj( $requestContent, $args ) );
        }

        public static function delete($args){

            if ( isset( $args['data'] ) ){
                $data = isset( $args['json'] ) && $args['json'] === true ? http_build_query( $args['data'] ):$args['data'];
            }else{
                $data = '';
            }

            $requestContent = array(
                CURLOPT_RETURNTRANSFER  =>  1,
                CURLOPT_CUSTOMREQUEST   =>  'DELETE',
                CURLOPT_URL             =>  $args['url'],
                CURLOPT_POSTFIELDS      =>  $data,    
                CURLOPT_SSL_VERIFYPEER  =>  2
            );

            return isset( $args['send'] ) && $args['send'] === false
                   ? self::prepareCurlObj( $requestContent, $args )
                   : self::getCurlResponse( self::prepareCurlObj( $requestContent, $args ) );
        }

        private static function getCurlResponse($args){
            $curl = curl_init();
            curl_setopt_array($curl,$args);
            $response           = curl_exec($curl);

            $responseContent = self::prepareResponseObj( $response, $curl );

            curl_close($curl);
            return $responseContent;
        }

        private static function prepareResponseObj( $response, $curl ){
            if ( !$response ){

                $curlInfo           = curl_getinfo( $curl );

                $responseContent    = [
                    'hasError'      => true, 
                    'errorMessage'  => curl_error( $curl ), 
                    'statusCode'    => $curlInfo['http_code']
                ];
            }else{
                $responseContent    = [
                    'scalar'        =>  $response,
                    'httpCode'      =>  curl_getinfo( $curl, CURLINFO_HTTP_CODE )
                ];
            }

            return is_array( $responseContent ) ? (object) $responseContent:$responseContent;
        }

        private static function prepareCurlObj( $requestContent,  $args = [] ){
            
            if ( isset($args['timeout']) ){
                $requestContent[CURLOPT_CONNECTTIMEOUT] = isset($args['reconnectTimeout']) && !empty( $args['reconnectTimeout'] ) ? $args['reconnectTimeout']:0;
                $requestContent[CURLOPT_TIMEOUT] = $args['timeout']; 
            }

            if ( isset($args['headers']) ){
                $requestContent[CURLOPT_HTTPHEADER] = $args['headers'];
                $requestContent[CURLINFO_HEADER_OUT] = true;
            }

            if ( isset($args['withProxy']) ){
                $requestContent[CURLOPT_PROXY] = $args['withProxy'];
                //$requestContent[CURLOPT_HTTPPROXYTUNNEL] = true;

                if ( isset($args['proxyType']) ){
                    $requestContent[CURLOPT_PROXYTYPE] = $args['proxyType'];
                }

                if ( isset($args['proxyAuth']) && is_array( $args['proxyAuth'] ) ){
                    $requestContent[PROXYUSERPWD] = "{$args['proxyAuth']['username']}:{$args['proxyAuth']['password']}";
                }
            }

            if ( isset( $args['followRedirect'] ) ){
                $requestContent[CURLOPT_FOLLOWLOCATION] = $args['followRedirect'];       
            }

            if ( isset( $args['cookie'] ) ){

                $requestContent[CURLOPT_COOKIEJAR] = $args['cookie'];
                $requestContent[CURLOPT_COOKIEFILE] = $args['cookie'];
            }

            return $requestContent;
        }

        /**
         * 生成XML节点, 通过自迭代遍历所传入数组下所有数据
         * @param array $params 所要遍历的数据
         * @param int $level 用于计算并生成易于阅读格式的XML空格数 (可选)
         * 
         * @return string格式的xml信息
         */
        public static function buildNode($params, $level = null){

            $node_text  = '';
            $nxtLevel   = $level === null ? $level:$level + 1;
            if ($nxtLevel !== null){
                foreach ($params as $param => $value){
                    //是否含有下层节点
                    $spaces = str_repeat('   ' , $nxtLevel);
                    if (is_array($value)){
                        $node_text .= "\n$spaces<$param>" . self::buildNode($value, $nxtLevel) . "</$param>\n";
                    }else{
                        $node_text .= "$spaces<$param>$value</$param>\n";
                    }
                }    
            }else{

                $isAssoicative  = self::hasStringKeys( $params );

                if ( $isAssoicative ){
                    foreach ($params as $param => $value){
                        //是否含有下层节点
                        if (is_array($value)){
                            $node_text .= "<$param>" . self::buildNode($value) . "</$param>";
                        }else{
                            $node_text .= "<$param>$value</$param>";
                        }
                    }    
                }else{

                    foreach ( $params as $arrayNode ){
                        $key        =   key( $arrayNode );
                        $node_text  .=  "<$key>". self::buildNode( $arrayNode[$key] ) ."</$key>";
                    }

                }

            }

            return $node_text;
        }

        public static function hasStringKeys(array $array) {
            return count(array_filter(array_keys($array), 'is_string')) > 0;
        }

        public static function isAssocArray(array $arr){

            if (array() === $arr) return false;
            return array_keys($arr) !== range(0, count($arr) - 1);
        }

    }

?>