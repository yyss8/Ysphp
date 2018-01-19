<?php 

    namespace Ysphp;

    Class Ajax_Handler{
    
        public function __construct($count = false) {

            $this->count        = $count;
            $this->currentData  = self::getCurrentMethodData();

            if ( isset($this->currentData['action']) ){
                $this->output   = isset($this->currentData['output']) ? $this->currentData['output']:false;
                $this->response = $this->fetchApiContent();
            }
        }

        //返回json
        public function json(){
            return $this->output ? '':json_encode($this->response);
        }
        
        //PHP默认只存储GET以及POST信息, 通过file_get_contents获取put以及delete的请求信息
        private static function getCurrentMethodData(){
            switch ( $_SERVER['REQUEST_METHOD'] ){
                case 'GET':
                    return $_GET;
                    break;
                case 'POST':
                    return $_POST;
                    break;
                case 'PUT':
                case 'DELETE':
                    parse_str(file_get_contents('php://input'), $data);
                    return $data;
                    break;
            }
        }

        private function fetchApiContent(){
            //获取api handler函数名称
            $function_name  = $_SERVER['REQUEST_METHOD'] . '_' .$this->currentData['action'];
            if ( $_SERVER['REQUEST_METHOD'] === 'PUT' ){

                global $_PUT;
                $_PUT = $this->currentData;
            }else if ( $_SERVER['REQUEST_METHOD'] === 'DELETE' ){
                global $_DELETE;
                $_DELETE = $this->currentData;
            }
            
            if ( function_exists( $function_name ) ){
                if ($this->count){
                    $start = microtime(true);
                }

                //如果output为true则不对api handler所输出的返回信息进行加工
                if ($this->output){
                    call_user_func($function_name);
                    return;
                }else{
                    $result         =   call_user_func($function_name);

                    $responseMessage = array(
                        'status'    =>  isset($result['error']) ? 'err':'ok',
                        'method'    =>  $_SERVER['REQUEST_METHOD'],
                        'result'    =>  isset($result['errorMessage']) ? $result['errorMessage']:$result
                    );
    
                    if ($this->count){
                        $time_elapsed_secs          = microtime(true) - $start;
                        $responseMessage['excute']  = $time_elapsed_secs;
                    }
    
                    return $responseMessage;
                }
            }

            return array(
                'status'    =>  'err',
                'content'   =>  'Unknown Ajax Action'
            );
        }
    }

?>