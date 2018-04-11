<?php 

    namespace Ysphp;

    class Qiniu{

        protected $url              = 'https://rs.qiniu.com'; //常用地址
        protected $upload_url       = 'https://upload-z1.qiniu.com/'; //上传地址

        /** 
        *   @param string $accessKey 七牛提供的访问key
        *   @param string $secretKey 七牛提供的私密key
        */
        public function __construct($accessKey, $secretKey){

            if ( $accessKey === '' ){
                throw new \Exception ('YSPHP: 七牛云访问密钥(accessKey)为空!');
            }

            if ( $secretKey === '' ){
                throw new \Exception ('YSPHP: 七牛云私密密钥(secretKey)为空!');
            }

            $this->accessKey = $accessKey;
            $this->secretKey = $secretKey;
        }

        //从外部设置密钥
        public function setKeys($accessKey, $secretKey){
            $this->accessKey = $accessKey;
            $this->secretKey = $secretKey;
        }

        //获取上传凭证
        protected function fetchUploadToken($params = array()){
            //加密组合凭证
            $encodedParams  = self::safeEncode( json_encode( $params ) );
            $signedParams   = hash_hmac( 'sha1', $encodedParams , $this->secretKey, true);
            $encodedSign    = self::safeEncode( $signedParams );
            return "{$this->accessKey}:$encodedSign:$encodedParams";
        }

        protected function fetchRegularToken($entry){
            $signedEntry    = hash_hmac( 'sha1', $entry , $this->secretKey, true);
            $encodedSign    = self::safeEncode( $signedEntry );
            return "{$this->accessKey}:$encodedSign";
        }

        //通过图片链接并转换为base64通过七牛base64接口上传
        public function uploadByUrl($bucket, $url, $params = array()){

            $fetchParams    = array(
                'url'       =>  $url
            );

            if ( isset($params['withProxy']) ){
                $fetchParams['withProxy'] = $params['withProxy'];
            }

            $fetchResponse  = Ys_Global::get( $fetchParams );
            $file           = $fetchResponse->scalar;
            $fileSize       = strlen( $file );
            //检测是否文件名为空, 如果为空则获取链接文件名称并通过随机数避免重复名称
            
            //处理上传参数
            $_params        = self::haveUploadParamsReady( $bucket, $url, $params );
            $_filename      = $_params['newName'];
            unset( $_params['newName'] );

            //获取上传凭证
            $token          = $this->fetchUploadToken( $_params );
            $authorization  = "UpToken $token";
            $encodedKey     = self::safeEncode( $_filename );
            $uploadUrl      = $this->upload_url . "putb64/$fileSize/key/$encodedKey"; 

            $postContent    = array(
                'url'           =>  $uploadUrl,
                'data'          =>  base64_encode( $file ),
                'headers'       =>  array(
                    'Content-Type: application/octet-stream',
                    "Authorization: $authorization"
                )
            );
            $response       = Ys_Global::post( $postContent );

            return json_decode( $response->scalar );
        }

        public function uploadByFilePath($bucket, $path = '' , $params = array()){

            $file           = file_get_contents( $path );
            $fileSize       = strlen( $file );
            //检测是否文件名为空, 如果为空则获取链接文件名称并通过随机数避免重复名称

            //处理上传参数
            $_params        = self::haveUploadParamsReady( $bucket, $path, $params );
            $_filename      = $_params['newName'];
            unset( $_params['newName'] );

            //获取上传凭证
            $token          = $this->fetchUploadToken( $_params );
            $authorization  = "UpToken $token";
            $encodedKey     = self::safeEncode( $_filename );
            $uploadUrl      = $this->upload_url . "putb64/$fileSize/key/$encodedKey"; 

            $postContent    = array(
                'url'           =>  $uploadUrl,
                'data'          =>  base64_encode( $file ),
                'headers'       =>  array(
                    'Content-Type: application/octet-stream',
                    "Authorization: $authorization"
                )
            );
            $response       = Ys_Global::post( $postContent );

            return json_decode( $response->scalar );
        }

        //通过$_FILES上传图片
        public function uploadByFile($bucket, $file_name = '', $params = array()){
            return $this->uploadByFilePath( $bucket, $_FILES[$file_name]['tmp_name'], $params );
        }

        //删除资源
        public function delete($bucket, $key){

            $encodedEntry   = self::safeEncode( "$bucket:$key" );
            $query          = "/delete/$encodedEntry";
            $token          = $this->fetchRegularToken( "$query\n" );
            $authorization  = "QBox $token";
            $postContent    = array(
                'url'           =>  $this->url . $query,
                'headers'       =>  array(
                    'Content-Type: application/x-www-form-urlencoded',
                    "Authorization: $authorization"
                )
            );
            $response       = Ys_Global::post( $postContent );
            return json_decode( $response->scalar );       
        }

        /**
         * 七牛云批量操作
         * @param array $resource 批量内容
         * @param string $action 操作类型
         */
        public function batchAction( $resources, $action ){

            if ( !$action || $action === ''){
                throw new \Exception ('YSPHP: 批量指令不能为空!');
            }

            if ( !is_array( $resources ) || sizeof( $resources ) === 0 ){
                throw new \Exception ('YSPHP: 欲删除资源列表为空!');
            }

            $encodedEntries = array_map( function ($resource) use($action){
                return "op=/$action/" . self::safeEncode( "{$resource['bucket']}:{$resource['key']}" );
            },$resources);
        
            $query          = '/batch?' . implode( '&', $encodedEntries);
            $token          = $this->fetchRegularToken( "$query\n" );
            $authorization  = "QBox $token";
            $postContent    = array(
                'url'           =>  $this->url . $query,
                'headers'       =>  array(
                    'Content-Type: application/x-www-form-urlencoded',
                    "Authorization: $authorization"
                )
            );
            $response       = Ys_Global::post( $postContent );
            if ( !$response->hasError ){
                return json_decode( $response->scalar );    
            }
            
            return $resource->errorMessage;

        }

        //更改文件名称
        public function changeFileName( $params = array() ){

            $previousEntry  = self::safeEncode( "{$params['previousBucket']}:{$params['previousKey']}" );
            $newEntry       = self::safeEncode( "{$params['newBucket']}:{$params['newKey']}" );
            $query          = "/move/$previousEntry/$newEntry/force/false";
            $token          = $this->fetchRegularToken( "$query\n" );
            $authorization  = "QBox $token";
            $postContent    = array(
                'url'           =>  $this->url . $query,
                'headers'       =>  array(
                    'Content-Type: application/x-www-form-urlencoded',
                    "Authorization: $authorization"
                )
            );

            $response       = Ys_Global::post( $postContent );
            return json_decode( $response->scalar );
        }

        public static function haveUploadParamsReady($bucket, $path, $params = array()){
            
            $_filename  = isset($params['fileName']) && $params['fileName'] !== '' ? $params['fileName'] : self::getRandomKey($path);
            //处理凭证参数
            $_params     = array(
                'scope'         =>  "$bucket:$_filename",
                'newName'       =>  $_filename,
                'deadline'      =>  isset( $params['deadline'] ) && self::isTimestampValid($params['deadline']) ? $params['deadline'] : strtotime( 'now +30 min' )
            );

            if ( isset( $params['insertOnly']) && $params['insertOnly'] === true ){
                $_params['insertOnly'] = 1;
            }
            if ( isset($params['fileType']) && $params['fileType'] === 'low' ){
                $_params['fileType'] = 1;
            }
            if ( isset($params['uploadType']) && $params['uploadType'] !== ''){
                $_params['detectMime']  = 1;
                $_params['mimeLimit']   = $params['uploadType'];
            }
            if ( isset($params['returnUrl']) && isset($params['returnBody']) ){
                $_params['returnUrl']   = $params['returnUrl'];
                $_params['returnBody']  = is_array( $params['returnBody'] ) ? json_encode( $params['returnBody'] ):$params['returnBody'];
            }
            if ( isset($params['callbackUrl']) && isset($params['callbackBody']) ){
                $_params['callbackUrl']     = $params['callbackUrl'];
                $callbackBodyType           = isset( $params['callbackBodyType'] ) ? strtoupper( $params['callbackBodyType'] ):'JSON';

                switch ( $callbackBodyType ){
                    case 'AJAX':
                    case 'APPLICATION/X-WWW-FORM-URLENCODED':
                        $_params['callbackBodyType']    = 'application/x-www-form-urlencoded';
                        $_params['callbackBody']        = is_array( $params['callbackBody'] ) ? http_build_query( $params['callbackBody'] ):$params['callbackBody'];
                        break;
                    case 'JSON':
                    case 'APPLICATION/JSON':
                    default:
                        $_params['callbackBody']        = is_array( $params['callbackBody'] ) ? json_encode( $params['callbackBody'] ):$params['callbackBody'];
                        $_params['callbackBodyType']    = 'application/json';
                }
            }
            if ( isset($params['fsizeMin']) && $params['fsizeMin'] !== '' ){
                $_params['fsizeMin'] = $params['fsizeMin'];
            }
            if ( isset($params['fsizeLimit']) && $params['fsizeLimit'] !== '' ){
                $_params['fsizeLimit'] = $params['fsizeLimit'];
            }

            return $_params;
        }

        //根据时间随机生成不重复文件名并替换-为_避免与七牛云图片样式冲突
        public static function getRandomKey($orgUrl = '' , $prefix = ''){
            $randomTxt      = date('mdHi');
            $_randomTxt     = $orgUrl === '' ? $randomTxt . rand(0,200) : $randomTxt . rand(0,99) . basename($orgUrl);
            $randomKey      = str_replace( '-', '_', substr( md5( $_randomTxt ), 0 , 17 ) . $randomTxt);
            return $prefix . '_' . $randomKey;
        }

        //转码为七牛云可接收的格式
        public static function safeEncode($content){
            return str_replace( array( '+','/' ), array( '-','_' ), base64_encode( $content ) ); 
        }

        //解码七牛云信息
        public static function safeDecode( $content){
            return base64_decode(  str_replace( array( '-','_' ), array( '+','/' ), $content )  );
        }

        //检测传入时间是否正确
        private static function isTimestampValid($time){
            return ((string) (int) $timestamp === $timestamp) 
                                    && ($timestamp <= PHP_INT_MAX)
                                    && ($timestamp >= ~PHP_INT_MAX);
        }

    }

?>