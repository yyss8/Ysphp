<?php 

    namespace Ysphp;

    class Baidu_Translate{

        protected $api_entry = 'http://api.fanyi.baidu.com/api/trans/vip/translate';
        protected $ssl_entry = 'https://fanyi-api.baidu.com/api/trans/vip/translate';

        /**
         * @param appId 百度翻译管理页面所提供App ID
         * @param secret 百度翻译管理页面所提供的密钥
         * @param ssl 是否启用SSL接口
         */
        public function __construct($appId, $secret, $ssl = false){

            if ( empty( $appId ) ){
                throw new \Exception('Ysphp: Baidu App ID cannot be empty.');
            }

            if ( empty( $secret ) ){
                throw new \Exception('Ysphp: Baidu Secret Key cannot be empty.');
            }

            $this->appId = $appId;
            $this->secretKey = $secret;
            $this->ssl = $ssl;
        }

        /**
         * 获取验证请求md5
         * 拼接规则: appid+q+salt+密钥
         */
        public function getSignedMessage( $query, $salt = null ){

            if (empty( $query ) ){
                throw new \Exception('Ysphp: Translating content cannot be empty.');
            }

            $_salt = empty( $salt ) ? $salt:$this->generateSalt();
            return md5( $this->appId . $query . $_salt . $this->secretKey );
        }

        public function generateSalt( $anotherSalt = null){
            return empty( $anotherSalt ) ? strtotime( 'now' ):$anotherSalt;
        }

        public function translate( $content, $params = array() ){

            if ( empty( $content ) ){
                throw new \Exception('Ysphp: Translating content cannot be empty.');
            }

            $salt       = $this->generateSalt();
            $default    = array(
                'from'          =>  'auto',
                'to'            =>  'zh',
                'appid'         =>  $this->appId,
                'salt'          =>  $salt,
            );

            //覆盖默认参数
            foreach( $params as $key => $value ){
                if ( !empty( $value ) ){
                    $default[$key] = $value;
                }
            }

            $isMultiple = false;
            if ( is_array( $content ) ){
                $isMultiple     = true;
                $_content       = urlencode( implode( "\n", $content ) );
            }else{
                $_content       = urlencode( $content );
            }

            $default['q']       = $_content;
            $sign               = $this->getSignedMessage( $_content, $default['salt']);
            $default['sign']    = $sign;

            $response           = Utilities::post( array(
                'url'               =>  $this->ssl ? $this->ssl_entry:$this->api_entry,
                'data'              =>  $default
            ));

            if ( isset( $response->hasError ) ){
                return array(
                    'error'             =>  true,
                    'errorMessage'      =>  $response->errorMessage
                );
            }

            $baiduResponse      = json_decode( $response->scalar, TRUE ); 

            if ( isset( $baiduResponse['error_code'] ) ){
                return array(
                    'error'         =>  true,
                    'errorcode'     =>  $baiduResponse['error_code'],
                    'errorMessage'  =>  $baiduResponse['error_msg']
                );
            }

            $translated             =   $baiduResponse['trans_result'][0];

            return array(
                'error'             =>  false,
                'src'               =>  $isMultiple ? explode( '%5Cn',  $translated['src']  ) : $translated['src'],
                'dst'               =>  $isMultiple ? explode( '5CN', $translated['dst'] ): $translated['dst']
            );
        }
    }
?>