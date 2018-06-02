<?php 

    namespace Ysphp;

    class Youdao {

        protected $api_entry = 'http://openapi.youdao.com/api';
        protected $ssl_entry = 'https://openapi.youdao.com/api';

        /**
         * @param appId 有道管理页面所提供App ID
         * @param secret 有道管理页面所提供的密钥
         * @param ssl 是否启用SSL接口
         */
        public function __construct($appKey, $secret, $ssl = false){

            if ( empty( $appKey ) ){
                throw new \Exception('Ysphp: Youdao App Key cannot be empty.');
            }

            if ( empty( $secret ) ){
                throw new \Exception('Ysphp: Youdao Secret Key cannot be empty.');
            }

            $this->appKey = $appKey;
            $this->secretKey = $secret;
            $this->ssl = $ssl;
        }

        /**
         * 获取验证请求md5
         * 拼接规则: appKey+q+salt+密钥
         */
        public function getSignedMessage( $query, $salt = null ){
            if (empty( $query ) ){
                throw new \Exception('Ysphp: Translating content cannot be empty.');
            }

            $_salt = empty( $salt ) ? $salt:$this->generateSalt();
            return md5( $this->appKey . $query . $_salt . $this->secretKey );
        }

        public static function generateSalt( $anotherSalt = null){
            return empty( $anotherSalt ) ? strtotime( 'now' ):$anotherSalt;
        }

        public static function encodeMultipleContent( $content, $encode = false){
            $_content = implode( "\n", array_map( function($ct)  use( $content, $encode ){
                return strip_tags( $ct );
            }, $content));
            return $encode ? rawurlencode( $_content ):$_content;
        }

        public static function decodeMultipleContent( $content){
            return array_map( function($ct) use ($content){
                return $ct;
            }, explode( "\n", $content));
        }

        /**
         * 翻译函数
         * @param mix $content 可传入数组或单string
         * @return mix
         */
        public function translate( $content , $params = array()){

            if ( empty( $content ) ){
                throw new \Exception('Ysphp: Translating content cannot be empty.');
            }

            $salt       = self::generateSalt();
            $default    = array(
                'from'          =>  'auto',
                'to'            =>  'auto',
                'appKey'        =>  $this->appKey,
                'salt'          =>  $salt,
                'only'          =>  'translate'
            );

            //覆盖默认参数
            foreach( $params as $key => $value ){
                if ( !empty( $value ) ){
                    $default[$key] = $value;
                }
            }

            $isMultiple         = false;
            if ( is_array( $content ) ){
                $isMultiple     = true;
                $_content       = self::encodeMultipleContent( $content );
    
            }else{
                $_content       = rawurlencode( strip_tags( $content ) );
            }

            $default['q']       = rawurlencode( $_content );

            $sign               = $this->getSignedMessage(  $_content , $salt);
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

            $youdaoReponse      = json_decode( $response->scalar, TRUE ); 

            if ( isset( $youdaoReponse['errorCode'] ) && $youdaoReponse['errorCode'] != '0'){
                return array(
                    'error'         =>  true,
                    'errorcode'     =>  self::getErrorMessageByCode( $youdaoReponse['errorCode'] ),
                );
            }

            if ( $isMultiple ){
                $srcs       = self::decodeMultipleContent( $youdaoReponse['query'] );
                $dst        = self::decodeMultipleContent( $youdaoReponse['translation'][0] );

                return array(
                    'error'     =>  false,
                    'src'       =>  $srcs,
                    'dst'       =>  $dst
                );
            }

            return array(
                'error'         =>  false,
                'dst'           =>  $youdaoReponse['translation'][0],
                'src'           =>  $youdaoReponse['query']
            );
        }

        public static function getErrorMessageByCode( $code ){
            switch ( $code ){
                case '103':
                    return '翻译文本过长';
                case '105':
                    return '不支持的签名类型';
                case '108':
                    return 'appKey无效';
                case '113':
                    return '翻译内容为空';
                case '202':
                    return '签名检验失败';
                case '203':
                    return '访问IP地址不在可访问IP列表';
                case '205':
                    return '请求的接口与应用的平台类型不一致';
                case '303':
                    return '服务端的其它异常';
                case '401':
                    return '账户已经欠费';
                case '411':
                    return '访问频率受限,请稍后访问';
                case '412':
                    return '长请求过于频繁，请稍后访问';
                default:
                    return '未知错误';
            }
        }
    }

?>