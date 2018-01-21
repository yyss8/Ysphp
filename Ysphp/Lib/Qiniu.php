<?php 

    namespace Ysphp;

    class Qiniu{

        protected $url              = 'https://rs.qbox.me/'; //默认地址
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

        //通过图片链接并转换为base64通过七牛base64接口上传
        public function uploadByUrl($bucket, $url, $params = array()){

            //检测是否需要使用名字
            if ( !isset($params['fileName']) || $params['fileName'] === '' ){
                //获取链接文件名称并通过随机数避免重复名称, 如果原文件名存在则替换其中的-字符避免与七牛云样式分隔符冲突
                $randomTxt      = date('mdHi');
                $basename       = basename( $url );
                $orgName        = $basename === '' ? "product$randomTxt": "$randomTxt$basename";
                $_filename      = str_replace( '-', '_', substr( md5( $orgName ), 0 , 17 ) . $randomTxt);
            }else{
                $_filename = $fileName;
            }

            //凭证参数
            $_params     = array(
                'scope'         =>  isset( $params['fileName'] ) ? "$bucket:$_filename":$bucket,
                'deadline'      =>  isset( $params['deadline'] ) && self::is_valid_timestamp($params['deadline']) ? $params['deadline'] : strtotime( 'now +30 min' ),
                'insertOnly'    =>  isset( $params['insertOnly']) && $params['insertOnly'] ? 1:0,
                'detectMime'    =>  '1',
                'mimeLimit'     =>  'image/*',
                'fileType'      =>  isset( $params['storeType'] ) && $params['storeType'] === 'low' ? 1:0
            );

            //上传格式检测
            if ( isset($params['uploadType']) && $params['uploadType'] !== ''){
                $_params['detectMime']  = 1;
                $_params['mimeLimit']   = $params['uploadType'];
            }

            //返回链接以及参数
            if ( isset($params['returnUrl']) && isset($params['returnBody']) ){
                $_params['returnUrl']   = $params['returnUrl'];
                $_params['returnBody']  = is_array( $params['returnBody'] ) ? json_encode( $params['returnBody'] ):$params['returnBody'];
            }

            //回调链接以及参数
            if ( isset($params['callbackUrl']) && isset($params['callbackBody']) ){
                $_params['callbackUrl']     = $params['callbackUrl'];
                $callbackBodyType           = isset( $params['callbackBodyType'] ) ? strtoupper( $params['callbackBodyType'] ):'JSON';

                //处理不同返回格式回调数据, 全部以大写检测
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

            //检测上传文件最小size, 单位为byte
            if ( isset($params['fsizeMin']) && $params['fsizeMin'] !== '' ){
                $_params['fsizeMin'] = $params['fsizeMin'];
            }

            //检测上传文件最大size, 单位为byte
            if ( isset($params['fsizeLimit']) && $params['fsizeLimit'] !== '' ){
                $_params['fsizeLimit'] = $params['fsizeLimit'];
            }
            
            //获取上传凭证
            $token          = $this->fetchUploadToken($bucket,  $_params );
            //获取文件
            $file           = file_get_contents( $url );
            $authorization  = "UpToken $token";
            $fileSize       = strlen( $file );
            $encodedKey     = self::safe_encode( $_filename );
            $uploadUrl      = $this->upload_url . "putb64/$fileSize/key/$encodedKey"; 

            //提交信息
            $postContent    = array(
                'url'           =>  $uploadUrl,
                'data'          =>  base64_encode( $file ),
                'headers'       =>  array(
                    'Content-Type: application/octet-stream',
                    "Authorization: $authorization"
                )
            );
            $response       = Ys_Global::post( $postContent );

            return array(
                'key'       =>  $_filename,
                'response'  =>  json_decode( $response->scalar )
            );
        }

        public function uploadByFile($bucket, $fileName = ''){
            return Ys_Global::get( $this->upload_url );
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