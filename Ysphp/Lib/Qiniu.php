<?php 

    namespace Ysphp;

    class Qiniu{

        protected $url              = 'https://rs.qiniu.com'; //常用地址
        protected $upload_url       = 'https://upload-z1.qiniu.com/'; //上传地址
        const GENERAL_URL           = 'https://rs.qbox.me';

        /** 
        *   @param string $accessKey 七牛提供的访问key
        *   @param string $secretKey 七牛提供的私密key
        */
        public function __construct($accessKey, $secretKey, $params = []){

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

        public function getBuckets(){

            $query          = '/buckets';
            $token          = $this->fetchRegularToken( "$query\n" );
            $authorization  = "QBox $token";
            $sending        = [
                'url'           =>  self::GENERAL_URL . $query,
                'headers'       =>  array(
                    'Content-Type: application/x-www-form-urlencoded',
                    "Authorization: $authorization"
                )
            ];

            $response       = Utilities::get( $sending );

            return json_decode( $response->scalar );
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

        public function uploadMultiByFile( $bucket, $fileVar, $params = [] ){

            if ( !isset( $bucket[0] ) ){
                throw new \Exception ('YSPHP: 上传bucket不能为空');
            }
            if ( !isset( $_FILES[$fileVar] )  || !isset( $_FILES[$fileVar]['name'] ) ){
                throw new \Exception ('YSPHP: 上传文件不能为空');
            }

            $uploadContent  = [];
            $fileObj        = $_FILES[$fileVar];
            $fileCount      = sizeof( $fileObj['name'] );

            if ( $fileCount <= 0 ){
                throw new \Exception ('YSPHP: 上传文件不能为空');
            }

            for ( $i = 0 ; $i < $fileCount; ++$i ){

                $file               = file_get_contents( $fileObj['tmp_name'][$i] );
                $fileSize           = $fileObj['size'][$i];
                $_params            = self::haveUploadParamsReady( $bucket, $fileObj['tmp_name'][$i] , $params );
                $_filename          = $_params['newName'];
                unset( $_params['newName'] );

                //获取上传凭证
                $token          = $this->fetchUploadToken( $_params );
                $authorization  = "UpToken $token";
                $encodedKey     = self::safeEncode( $_filename );
                $uploadUrl      = $this->upload_url . "putb64/$fileSize/key/$encodedKey"; 

                $uploadContent[] = [
                    'args'          =>  Utilities::post([
                        'url'           =>  $uploadUrl,
                        'data'          =>  base64_encode( $file ),
                        'headers'       =>  [
                            'Content-Type: application/octet-stream',
                            "Authorization: $authorization"
                        ],
                        'send'          =>  false
                    ])
                ];

            }

            if ( !isset( $uploadContent[0] ) ){
                return [
                    'error'         =>  true,
                    'errorMessage'  =>  '无任何有效文件'
                ];
            }

            $uploadResponses     = Utilities::multiCurl( $uploadContent );

            foreach ( $uploadResponses as $uploadResponse ){
                $responseContents[$uploadResponse->index] = json_decode($uploadResponse->response);
            }

            return $responseContents;

        }

        /**
         * 通过图片链接上传多个图片, 只通过回调函数返回key
         * @param string $bucket 
         * @param array $urls 链接
         * @param array $params optional 上传文件参数
         * @param function $callback 回调函数
         * 
         * @return void
         */
        public function uploadMultiByUrl( $bucket, $urls, $params = []){

            if ( !isset( $bucket[0] ) ){
                throw new \Exception ('YSPHP: 上传bucket不能为空');
            }

            if ( !isset( $urls[0] ) ){
                throw new \Exception ('YSPHP: 上传链接不能为空');
            }

            $responseContents   = [];
            $uploadContent      = [];

            $baseFetchParams    = [
                'followRedirect'    => true,
                'send'              => false,
                'headers'           => [
                    'user-agent: Mozi lla/4.0 (compatible; MSIE 6.0)'
                ],
                'timeout'           => 120
            ];

            if ( isset($params['withProxy']) ){
                $baseFetchParams['withProxy'] = $params['withProxy'];

                if ( isset( $params['proxyType'] ) ){
                    $baseFetchParams['proxyType'] = $params['proxyType'];
                }
            }

            //分离成多块避免造成太多请求被请求服务器拒绝
            $chunked = isset( $params['chunk'] ) ? array_chunk( $urls, intval( $params['chunk'] ) ):[ $urls ];

            foreach ( $chunked as $chunkUrls ){
                //先获取所有链接图片内容
                $preparedFetchJobs = [];
                foreach ( $chunkUrls as $url ){
                    if ( empty( $url ) ){
                        continue;
                    }
            
                    $baseFetchParams['url'] = is_array( $url ) ? $url['url']:$url;
                    $preparedFetchJobs[]    = [
                        'args'          =>  Utilities::get( $baseFetchParams )
                    ];
                }

                $response = Utilities::multiCurl( $preparedFetchJobs);

                foreach ( $response as $index => $result){
        
                    if ( isset( $result->hasError )  && $result->hasError === true){
                        $responseContents[$index] = [
                            'error'         =>  true,
                            'errorMessage'  =>  $result->errorMessage
                        ];
                        continue;
                    }

                    if ( isset( $chunkUrls[$index]['fileName'] ) ){
                        $params['fileName'] = $chunkUrls[$index]['fileName'];
                    }

                    $fileSize           = strlen( $result->response );
                    $_params            = self::haveUploadParamsReady( $bucket, $chunkUrls[$index], $params );
                    $_filename          = $_params['newName'];
                    unset( $_params['newName'] );

                    //获取上传凭证
                    $token          = $this->fetchUploadToken( $_params );
                    $authorization  = "UpToken $token";
                    $encodedKey     = self::safeEncode( $_filename );
                    $uploadUrl      = $this->upload_url . "putb64/$fileSize/key/$encodedKey"; 

                    $uploadContent[] = [
                        'args'          =>  Utilities::post([
                            'url'           =>  $uploadUrl,
                            'data'          =>  base64_encode( $result->response ),
                            'headers'       =>  [
                                'Content-Type: application/octet-stream',
                                "Authorization: $authorization"
                            ],
                            'send'          =>  false
                        ])
                    ];
                }
            }

            //统一上传
            if ( !isset( $uploadContent[0] ) ){
                return [
                    'error'         =>  true,
                    'errorMessage'  =>  '无任何有效文件'
                ];
            }
        
            $uploadResponses     = Utilities::multiCurl( $uploadContent );

            foreach ( $uploadResponses as $uploadResponse ){
                $responseContents[$uploadResponse->index] = json_decode($uploadResponse->response);
            }

            return $responseContents;
        }

        //通过图片链接并转换为base64通过七牛base64接口上传
        public function uploadByUrl($bucket, $url, $params = array()){

            $fetchParams    = array(
                'url'               =>  $url,
                'followRedirect'    =>  true
            );

            if ( isset($params['withProxy']) ){
                $fetchParams['withProxy'] = $params['withProxy'];
            }

            $fetchResponse  = Utilities::get( $fetchParams );
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
            $response       = Utilities::post( $postContent );

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
            $response       = Utilities::post( $postContent );
            if ( isset( $response->hasError ) && $response->hasError === true ){
                return (object) array(
                    'error'         =>  true,
                    'errorMessage'  =>  $response->errorMessage
                );
            }

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
            $response       = Utilities::post( $postContent );

            if (  isset( $response->hasError ) && !$response->hasError ){
                return array(
                    'error'         =>  true,
                    'errorMessage'  =>  $response->errorMessage
                );
            }

            return true;
        }

        /**
         * 七牛云批量操作
         * @param array $resource 批量内容
         * @param string $action 操作类型
         */
        public function batchAction( $resources, $action ){

            if ( !$action || $action === ''){
                throw new \Exception('YSPHP: 批量指令不能为空!');
            }

            if ( !is_array( $resources ) || sizeof( $resources ) === 0 ){
                throw new \Exception('YSPHP: 欲操作资源列表为空!');
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
            $response       = Utilities::post( $postContent );
            
            if ( isset( $response->hasError ) && !$response->hasError ){
                return array(
                    'error'         =>  true,
                    'errorMessage'  =>  $response->errorMessage
                );
            }
            
            return json_decode( $response->scalar );    

        }

        /**
         * 更改文件名称或移动资源
         */
        public function move( $params = [] ){

            if ( empty( $params ) ){
                throw new \Exception ('YSPHP: 传入参数为空');
            }

            if ( !isset( $params['previousBucket'] ) || !isset( $params['previousKey'] ) ){
                throw new \Exception ('YSPHP: 原内容为空');
            }

            if ( !isset( $params['newBucket'] ) || !isset( $params['newKey'] ) ){
                throw new \Exception ('YSPHP: 目标内容为空');
            }

            $force = isset( $params['force'] ) && $params['force'] === true;

            $previousEntry  = self::safeEncode( "{$params['previousBucket']}:{$params['previousKey']}" );
            $newEntry       = self::safeEncode( "{$params['newBucket']}:{$params['newKey']}" );
            $query          = "/move/$previousEntry/$newEntry" . ( $force ? '/force/true':'' );
            $token          = $this->fetchRegularToken( "$query\n" );
            $authorization  = "QBox $token";
            $postContent    = array(
                'url'           =>  $this->url . $query,
                'headers'       =>  array(
                    'Content-Type: application/x-www-form-urlencoded',
                    "Authorization: $authorization"
                )
            );

            $response       = Utilities::post( $postContent );

            if ( isset( $response->hasError ) && !$response->hasError ){
                return array(
                    'error'         =>  true,
                    'errorMessage'  =>  $response->errorMessage
                );
            }

            return json_decode( $response->scalar );
        }

        public static function haveUploadParamsReady($bucket, $path, $params = []){
            
            $_filename  = !empty( $params['fileName'] ) ? $params['fileName'] : self::getRandomKey($path, isset( $params['prefix'] ) ? $params['prefix']:'' );
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