<?php

    namespace Ysphp;

    class Validation{

        const RID_CITIES = [
            '11'    =>  '北京'  , '12'    =>  '天津'  , '13'    =>  '河北',
            '14'    =>  '山西'  , '15'    =>  '内蒙古', '21'    =>  '辽宁',
            '22'    =>  '吉林'  , '23'    =>  '黑龙江', '31'    =>  '上海',
            '32'    =>  '江苏'  , '33'    =>  '浙江'  , '34'    =>  '安徽',
            '35'    =>  '福建'  , '36'    =>  '江西'  , '37'    =>  '山东',
            '41'    =>  '河南'  , '42'    =>  '湖北'  , '43'    =>  '湖南',
            '44'    =>  '广东'  , '45'    =>  '广西'  , '46'    =>  '海南',
            '50'    =>  '重庆'  , '51'    =>  '四川'  , '52'    =>  '贵州',
            '53'    =>  '云南'  , '54'    =>  '西藏'  , '61'    =>  '陕西',
            '62'    =>  '甘肃'  , '63'    =>  '青海'  , '64'    =>  '宁夏',
            '65'    =>  '新疆'  , '71'    =>  '台湾'  , '81'    =>  '香港',
            '82'    =>  '澳门'  , '91'    =>  '国外'
        ];

        private $messages    = [
            'required'      =>  'Field %s is required',
            'password'      =>  'Password is empty or not strong enough',
            'email'         =>  'Invalid email address',
            'rid'           =>  'Invalid resident ID number',
            'min'           =>  'Field %s must be longer than %d characters',
            'max'           =>  'Field %s must be shorter than %d characters',
            'between'       =>  'Field %s must be between %d and %d characters',
            'number'        =>  'Field %s must be a number',
            'cell'          =>  'Invalid cell phone number',
            'cncell'        =>  'Invalid Chinese cell phone number',
            'default'       =>  'Invalid input value for %s'
        ];

        private $errors     = [];

        /**
         * @param array $fields 所要检查的内容
         * @param array $rules 所要检查规则
         */
        public function __construct( $fields ){

            if ( empty( $fields ) ){
                throw new \Exception( 'Ysphp: Validating fields is empty' );
            }
            
            $this->fields   = $fields;
            $this->errors   = $this->validate(); 

        }
     
        public function validate(){

            $errors     = [];
            foreach ( $this->fields as $field => $value){

                $hasCustomMessage = isset( $value['message'] );
                if ( !isset( $value['rule'] ) ){
                    if ( !$this->isRequiredValid( $value['value'] ) ){
                        $errors[]   = $hasCustomMessage 
                                    ? isset( $value['message']['required'] ) ? $value['message']['required']:$value['message']
                                    : $this->messages['required'];
                    }
                    continue;
                }

                if ( !is_array( $value['rule'] ) ){
                    if ( !$this->isFieldValid( $value['rule'], $value ) ){
                        $errors[]   = $hasCustomMessage
                                    ? (!isset( $value['message'][$value['rule']] ) ? $value['message']:$value['message'][$value['rule']])
                                    : $this->getMessage( $value['rule'] );
                    }

                }else{
                    foreach ( $value['rule'] as $fieldRule){
                        if ( !$this->isFieldValid( $fieldRule, $value ) ){
                            $errors[]   = $hasCustomMessage
                                        ? (!isset( $value['message'][$fieldRule] ) ? $value['message']:$value['message'][$fieldRule])
                                        : $this->getMessage( $fieldRule );
                            break;  
                        }
                    }
                }
            }

            return $errors;
        }

        public function isFieldValid( $rule, $value ){

            $methodName = "is_{$rule}_valid";

            if ( $rule === 'between' && isset( $value['maxValue'] ) && isset( $value['minValue'] )){
                return $this->is_between_vaild( $value['value'], $value['minValue'], $value['maxValue']  );
            }

            if ( method_exists( __CLASS__, $methodName ) ){

                if ( isset( $value['match'] ) ){
                    
                    if ( is_array( $value['match'] )  && isset( $value['match'][$rule] ) ){
                        return call_user_func( [__CLASS__, $methodName], $value['value'], $value['match'][$rule] );
                    }

                    return call_user_func( [__CLASS__, $methodName], $value['value'], $value['match'] );
                }

                return call_user_func( [__CLASS__, $methodName], $value['value'] );
            }

            return true;
        }

        public function is_number_valid( $num ){
            return filter_var( $num, FILTER_VALIDATE_INT );
        }

        public function is_between_vaild( $str, int $min, int $max){
            return isset( $str[$min] ) && !isset( $str[$max] );
        }

        public static function is_cncell_valid( $str ){
            return isset( $str[0] ) ? preg_match( "/^(13[0-9]|14[579]|15[0-3,5-9]|16[6]|17[0135678]|18[0-9]|19[89])\d{8}$/", $str ):'';
        }

        public function is_url_valid( $str ){
            return isset( $str[0] ) ? filter_var( $str, FILTER_VALIDATE_URL ):false;
        }

        public function is_max_valid( $str, int $max ){
            return !isset( $str[ $max ] );
        }

        public function is_min_valid( $str, int $min ){
            return isset( $str[ $min - 1 ] );
        }

        public function is_required_valid( $str ){
            return !empty( $str );
        }

        public static function is_email_valid( $email ){
            return filter_var( $email, FILTER_VALIDATE_EMAIL );
        }

        public static function is_alnum_vaild( $str ){
            return ctype_alnum( $str );
        }

        public function is_password_valid( $password , $reg = "/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}/"){
            return isset( $password[0] ) ? preg_match( $reg, $password ):false;
        }

        public function is_regexp_valid( $str, $regexp = ''){
            return !empty( $regexp[0] ) && isset( $str[0] ) ? preg_match( $regexp, $str):false;
        }

        public function is_nonregexp_valid( $str, $regexp = '' ){
            return !empty( $regexp ) && isset( $str[0] ) && !preg_match_all( $regexp, $str);
        }

        /**
         * 验证身份证
         * 来源 https://blog.csdn.net/websites/article/details/66969871
         */
        public function is_rid_valid( $rid ){

            if ( !isset( $rid[0] ) || !preg_match( "/^[1-9]\d{5}(18|19|([23]\d))\d{2}((0[1-9])|(10|11|12))(([0-2][1-9])|10|20|30|31)\d{3}[0-9Xx]$/" , $rid ) ){
                return false;
            }              

            if ( !isset( self::RID_CITIES[ substr( $rid, 0, 2 ) ] )){
                return false;
            }

            //18位身份证需要验证最后一位校验位  
            if ( isset( $rid[17] ) ){
                $char   = str_split( $rid );
                $factor = [ 7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2 ];
                $parity = [ 1, 0, 'X', 9, 8, 7, 6, 5, 4, 3, 2 ];
                $sum    = $ai = $wi = 0;
                for ( $i = 0; $i < 17; ++$i ){
                    $ai     = $char[$i];
                    $wi     = $factor[$i];
                    $sum    += $ai * $wi; 
                }
    
                if ( $parity[ $sum % 11 ] != strtoupper( $char[17] ) ){
                    return false;
                }
            }

            return true; 
        }

        public function setValidatingFields( $fields ){
            $this->fields = $fields;
            $this->validate();
            return $this;
        }

        public function hasError(){
            return isset( $this->errors[0] );
        }

        /** Setters */

        public function setMessages( $messages ){
            if ( is_array( $messages ) ){
                foreach ( $messages as $messageType => $message ){
                    $this->messages[ $messageType ] = $message;
                }
            }
        }

        /** Getters */
        public function getErrors(){
            return $this->errors;
        }

        public function getFirstError(){
            return isset( $this->errors[0] ) ? $this->errors[0]:false;
        }

        public function getMessage( $field ){
            return isset( $this->messages[$field] ) ? $this->messages[$field] : $this->messages['default'];
        }
    }