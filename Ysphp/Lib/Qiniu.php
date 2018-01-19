<?php 

    namespace Ysphp;

    class Qiniu{

        protected $url              = 'https://rs.qbox.me/';
        protected $upload_url       = 'https://upload-z1.qiniu.com/';

        public function __construct($accessKey, $secretKey){
            $this->accessKey = $accessKey;
            $this->secretKey = $secretKey;
        }

        //从外部设置密钥
        public function setKeys($accessKey, $secretKey){
            $this->accessKey = $accessKey;
            $this->secretKey = $secretKey;
        }

        //获取上传凭证
        protected function uploadToken($bucket, $params = array()){

            //key以及bucket
            $scope      = isset($params['fileName']) ? "$bucket:{$params['fileName']}":"$bucket";
            //凭证有效期
            $deadline   = isset($params['deadline']) ? $params['deadline']:strtotime('now +1 hour');
            $authParams = array(
                'scope'         =>  $scope,
                'insertOnly'    =>  isset($params['modify']) && $params['modify'] ? 2:1,
                'deadline'      =>  $deadline,
                'detectMime'    =>  '1',
                'mimeLimit'     =>  'image/*'
            );

            $authParams = array(
                'scope'         =>  $scope,
                'deadline'      =>  $deadline,
            );

            //加密组合凭证
            $encodedParams  = self::safe_encode( json_encode( $authParams ) );
            $signedParams   = hash_hmac( 'sha1', $encodedParams , $this->secretKey, true);
            $encodedSign    = self::safe_encode( $signedParams );
            return "{$this->accessKey}:$encodedSign:$encodedParams";
        }

        //通过图片链接并转换为base64通过七牛base64接口上传
        public function uploadByUrl($bucket, $url, $fileName = ''){

            //获取链接文件名称并通过随机数避免重复名称, 如果原文件名存在则替换其中的-字符避免与七牛云样式分隔符冲突
            $randomTxt      = date('YmHi') . rand(0,2000);
            $basename       = basename( $url );
            $_filename      = $basename === '' ? "product$randomTxt": "p$randomTxt" . str_replace('-','_',$basename);

            $params     = array(
                'fileName'  =>  $_filename,
                'deadline'  =>  strtotime( 'now +30 min' )
            );
            
            //获取上传凭证并准备放入header中
            $token          = $this->uploadToken($bucket,  $params );
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

            return $response;
        }

        public function uploadByPath($bucket, $path, $filename = ''){

        }

        public function uploadByFile($bucket, $fileName = ''){

        }

        //转码为七牛云可接收的格式
        public static function safe_encode($content){
            return str_replace( array( '+','/' ), array( '-','_' ), base64_encode( $content ) ); 
        }

        //解码七牛云信息
        public static function safe_decode( $content){
            return base64_decode(  str_replace( array( '-','_' ), array( '+','/' ), $content )  );
        }

    }

?>