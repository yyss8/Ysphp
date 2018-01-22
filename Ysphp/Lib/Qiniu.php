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
            $encodedParams  = self::safe_encode( json_encode( $params ) );
            $signedParams   = hash_hmac( 'sha1', $encodedParams , $this->secretKey, true);
            $encodedSign    = self::safe_encode( $signedParams );
            return "{$this->accessKey}:$encodedSign:$encodedParams";
        }

        protected function fetchRegularToken($entry){
            $signedEntry    = hash_hmac( 'sha1', $entry , $this->secretKey, true);
            $encodedSign    = self::safe_encode( $signedEntry );
            return "{$this->accessKey}:$encodedSign";
        }

        //通过图片链接并转换为base64通过七牛base64接口上传
        public function uploadByUrl($bucket, $url, $params = array()){

            //检测是否文件名为空, 如果为空则获取链接文件名称并通过随机数避免重复名称
            $_filename  = isset($params['fileName']) && $params['fileName'] !== '' ? $params['fileName'] : self::getRandomKey($url);

            //处理凭证参数
            $_params     = array(
                'scope'         =>  isset( $params['fileName'] ) ? "$bucket:$_filename":$bucket,
                'deadline'      =>  isset( $params['deadline'] ) && self::is_valid_timestamp($params['deadline']) ? $params['deadline'] : strtotime( 'now +30 min' )
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
            
            //获取上传凭证
            $token          = $this->fetchUploadToken( $_params );
            $file           = file_get_contents( $url );
            $authorization  = "UpToken $token";
            $fileSize       = strlen( $file );
            $encodedKey     = self::safe_encode( $_filename );
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
            return $this->uploadByUrl( $bucket, $_FILES[$file_name]['tmp_name'], $params );
        }

        //删除资源
        public function delete($bucket, $key){

            $encodedEntry   = self::safe_encode( "$bucket:$key" );
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

        //更改文件名称
        public function changeFileName( $params = array() ){

            $previousEntry  = self::safe_encode( "{$params['previousBucket']}:{$params['previousKey']}" );
            $newEntry       = self::safe_encode( "{$params['newBucket']}:{$params['newKey']}" );
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

        //根据时间随机生成不重复文件名并替换-为_避免与七牛云图片样式冲突
        public static function getRandomKey($orgUrl = ''){
            $randomTxt      = date('mdHi');
            $_randomTxt     = $orgUrl === '' ? $randomTxt . rand(0,200) : $randomTxt . rand(0,99) . basename($orgUrl);
            $randomKey      = str_replace( '-', '_', substr( md5( $_randomTxt ), 0 , 17 ) . $randomTxt);
            return $randomKey;
        }

        //转码为七牛云可接收的格式
        public static function safe_encode($content){
            return str_replace( array( '+','/' ), array( '-','_' ), base64_encode( $content ) ); 
        }

        //解码七牛云信息
        public static function safe_decode( $content){
            return base64_decode(  str_replace( array( '-','_' ), array( '+','/' ), $content )  );
        }

        //检测传入时间是否正确
        private static function is_valid_timestamp($time){
            return ((string) (int) $timestamp === $timestamp) 
                                    && ($timestamp <= PHP_INT_MAX)
                                    && ($timestamp >= ~PHP_INT_MAX);
        }

    }

?>