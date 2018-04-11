<?php 

    namespace Ysphp\Database;

    class Mysql {
        
        protected $connection;

        protected $dbConfig;

        protected $charset                                      = 'utf-8';

        public function __construct($config = array()){
            
            $configFormat = [
                'url'       =>  '',
                'username'  =>  '',
                'password'  =>  '',
                'database'  =>  ''
            ];

            if (!is_array($config)){
                throw new \Exception ('YSPHP: Empty Database Config Information.');
            }

            foreach ($configFormat as $key=>$value){
                if (!isset($config[$key])){
                    $k = ucfirst($key);
                    throw new \Exception ("YSPHP: Missing Database Parameter: {$k}");
                }else if(empty($config[$key])){
                    $k = ucfirst($key);
                    throw new \Exception ("YSPHP: Database Parameter '{$k}' Is Empty!");
                }
            }
            
            $connection = \mysqli_connect($config['url'], $config['username'], $config['password'], $config['database']);
            $this->charset = mysqli_character_set_name($connection);
            if ($connection){
                mysqli_set_charset($connection,'UTF-8');
                $this->connection = $connection;
                $this->dbConfig = $config;
            }
            
        }
        
        public function reInitConnection($config){

        }

        public function isConnected(){
            return $this->connection ? true:false;
        }

        public function resetDatabase($name){
            $this->connection->close();
            $config = $this->dbConfig;
            $config['database'] = $name;
            
            $connection = mysqli_connect($config['url'], $config['username'], $config['password'], $config['database']);
            try {
                if ($connection){
                    mysqli_set_charset($connection,'utf-8');
                    $this->connection = $connection;
                    $this->dbConfig = $config;
                    return true;
                }
            }catch (Exception $e){
                $connection = mysqli_connect($this->dbConfig['url'], $this->dbConfig['username'], $this->dbConfig['password'], $this->dbConfig['database']);
            }

            return false;
        }

        public function getHostInfo(){
            return mysqli_get_host_info($this->connection);
        }

        public function setChar($charset){
            $this->charset = $charset;
            mysqli_set_charset($connection,$charset);
        }

        public function forceClose(){
            mysql_close($this->connection);
        }

        //fetch one table row
        public function single($query, $options = array()){

            $response = array(
                'hasError'      =>  false,
                'content'       =>  null,
                'errorMessage'  =>  ''
            );

            if (!is_array($query)){

                if (empty($query)){
                    throw new \Exception ("YSPHP: Missing Mysql Query");
                }

                if ($this->charset !== 'utf8'){
                    mysqli_query($this->connection,'set names utf8'); 
                }
                $queryResult = mysqli_query($this->connection, $query);
                if ($queryResult){
                    $response['content']        = mysqli_fetch_assoc($queryResult);
                }else{
                    $response['hasError']       = true;
                    $response['errorMessage']   = mysqli_error($this->connection);
                }

            }else{

            }

            return (object) $response;
        }

        public function affected_rows(){
            return mysqli_affected_rows($this->connection);
        }

        //fetch multiple table rows
        public function multiple($query, $options = array()){

            $response = array(
                'hasError'      =>  false,
                'content'       =>  null,
                'errorMessage'  =>  '',
                'count'         =>  0
            );
            $hasKey             = isset($options['keyField']) && $options['keyField'] !== '';

            if (!is_array($query)){
                
                if ($this->charset !== 'utf8'){
                    mysqli_query($this->connection,'set names utf8'); 
                }

                $queryResult = mysqli_query($this->connection, $query);
                if ($queryResult){

                    $response['content'] = [];

                    while($r = mysqli_fetch_assoc($queryResult)){
                        if ($hasKey){
                            $keyValue = $r[$options['keyField']];
                            unset($r[$options['keyField']]);
                            $response['content'][$keyValue] = $r;
                        }else{
                            $response['content'][] = $r;
                        }
                        $response['count'] += 1;
                    }
                }else{
                    $response['hasError'] = true;
                    $response['errorMessage'] = mysqli_error($this->connection);
                }

            }
                
            return (object)$response;
        }

        //fetch single variable from query
        public function variable( $query ){

            $response = array(
                'hasError'      =>  false,
                'content'       =>  null,
                'errorMessage'  =>  ''
            );

            if ( empty( $query ) ){
                throw new \Exception ('YSPHP: Empty Query.');
            }

            if (!is_array($query)){
                    
                $queryResult    = mysqli_query($this->connection, $query);
                if (!$queryResult){
                    $response['hasError']       = true;
                    $response['errorMessage']   = mysqli_error($this->connection);
                }else{
                    $response['content']        = current((array)mysqli_fetch_object($queryResult));
                }

                return (object) $response; 

            }

        }

        public function truncate($table){
            if (empty($table)){
                throw new \Exception ('YSPHP: Uruncate Table Missing.');
            }

            $response = array(
                'hasError'      =>  false,
                'content'       =>  null,
                'errorMessage'  =>  '',
                'count'         =>  0
            );

            $query = "TRUNCATE TABLE $table";
            $queryResult = mysqli_query($this->connection, $query);
            
            if (!$queryResult){
                $response['hasError']       = true;
                $response['errorMessage']   = mysqli_error($this->connection);
            }

            return object( $response );
        }

        public function multiQuery($args){

            if (!is_array($args)){
                throw new \Exception ('YSPHP: multiQuery Method Requires First Parameter To Be Array.');
            }

            if (sizeof($args) === 0){
                throw new \Exception ('YSPHP: Empty Quries.');
            }

            $response = array(
                'hasError'      =>  false,
                'content'       =>  null,
                'errorMessage'  =>  '',
            );

            $queries        = implode(';', $args) . ';';
            $queryResult    = mysql_multi_query($this->connection,$queries);
            
            if (!$queryResult){
                $response['hasError']       = true;
                $response['errorMessage']   = mysqli_error($this->connection);
            }

            return (object)$response;
        }

        public function query($query){
            if (empty($query)){
                throw new \Exception ('YSPHP: Update Query Missing.');
            }

            return mysqli_query($this->connection, $query);
        }

        public function count($args = array()){

            $count = 0;

            if (is_array($args)){

                if (!isset($args['table']) || empty($args['table'])){
                    throw new \Exception ('YSPHP: Missing Count Query Parameter: Table');
                }
    
                $query          = $this->buildQuery('count', $args);
                $queryResult    = mysqli_query($this->connection, $query);
                if ($queryResult){
                    $queryCount = mysqli_fetch_assoc($queryResult);
                    $count = $queryCount['c'];
                }else{
                    $count = 0;
                }
            }else{
                if (empty($args)){
                    $queryResult = mysqli_query($this->connection, $args);
                    if ($queryResult){
                        $queryCount = mysqli_fetch_assoc($queryResult);
                        $count = $queryCount['c'];
                    }else{
                        $count = 0;
                    }
                }
            }
            
            return (int)$count;
        }

        public function update($args = ''){

            $response = array(
                'hasError'      =>  false,
                'content'       =>  null,
                'errorMessage'  =>  '',
            );

            if (!is_array($args)){

                if (empty($args)){
                    throw new \Exception ('YSPHP: Update Query Missing.');
                }

                $queryResult = mysqli_query($this->connection, $args);
                if (!$queryResult){
                    $response['hasError']       = true;
                    $response['errorMessage']   = mysqli_error($this->connection);
                }

            }else{
                if (!isset($args['table']) || empty($args['table'])){
                    throw new \Exception ('YSPHP: Missing Update Query Parameter: Table');
                }

                if (!isset($args['set']) || sizeof($args['set'])  === 0){
                    throw new \Exception ('YSPHP: Missing Update Query Parameter: Update Values');
                }

                $query          = $this->buildQuery('update', $args);
                $queryResult    = mysqli_query($this->connection, $query);

                if (!$queryResult){
                    $response['hasError']       = true;
                    $response['errorMessage']   = mysqli_error($this->connection);
                }else{
                    $response['content']        = mysqli_affected_rows( $this->connection );
                }

            }

            return (object) $response;
        }

        public function insert($table,$columns,$ignore = false){
            $query                  = $ignore ? 'INSERT IGNORE INTO':'INSERT INTO';
            
            $response = array(
                'hasError'      =>  false,
                'content'       =>  null,
                'errorMessage'  =>  '',
            );

            if (empty($table)){
                throw new \Exception ('YSPHP: Missing Insert Query Parameter: Table');
            }else{
                $query .= " $table (";
            }

            if (!$columns || sizeof($columns) === 0){
                throw new \Exception ('YSPHP: Missing Insert Query Parameter: Insert Data');
            }

            $keyArgs = [];
            $valueArgs = [];

            foreach ($columns as $key => $value){
                $keyArgs[] = " $key ";
                $valueArgs[] = is_numeric($value) ? " $value ":" '$value' ";
            }

            $query .= implode(',',$keyArgs) . ') VALUES (' . implode(',',$valueArgs) . ')';

            $qryResult = mysqli_query($this->connection, $query);
            if ($qryResult){
                $response['content']        = mysqli_insert_id($this->connection);
            }else{
                $response['hasError']       = true;
                $response['errorMessage']   = mysqli_error($this->connection);
            }

            return (object)$response;

        }

        public function insertMulti($args, $ignore = false){
            
            $query      = $ignore ? 'INSERT IGNORE INTO':'INSERT INTO';
            $response   = array(
                'hasError'      =>  false,
                'content'       =>  null,
                'errorMessage'  =>  '',
            );

            if (!isset($args['table']) || empty($args['table'])){
                throw new \Exception ('YSPHP: Missing Insert Query Parameter: Table');
            }

            if (!isset($args['value']) || !is_array($args['value'])){
                throw new \Exception ('YSPHP: Invalid Insert Query Parameter: value');
            }

            if (!isset($args['key']) || !is_array($args['key'])){
                throw new \Exception ("YSPHP: Invalid Insert Query Parameter: key");
            }

            $valueAry   = [];

            $keys       = implode(',',$args['key']);

            foreach ($args['value'] as $value){
                $quotedValue = array_map(function($v) use($value){
                    return "'$v'";
                }, $value);
                $valueAry[] = '(' . implode(',',$quotedValue) . ')';
            }
            
            $values = implode(',' , $valueAry);
            $query = sprintf('%s %s (%s) VALUES %s ', $query,$args['table'],$keys,$values);
            $qryResult = mysqli_query($this->connection, $query);
        
            if ($qryResult){
                $result['content']      = mysql_insert_id($this->connection);
            }else{
                $result['hasError']     = true;
                $result['errorMessage'] = mysqli_error($this->connection);
            }

            return (object) $response;

        }
    
        public function create($args){

            $response   = array(
                'hasError'      =>  false,
                'content'       =>  null,
                'errorMessage'  =>  '',
            );

            if (is_array($args)){
                if (!isset($args['table'])){
                    throw new \Exception ('YSPHP: Missing Create Query Parameter: Table');
                }


                if (!isset($args['value']) || sizeof($args['value']) === 0){
                    throw new \Exception ('YSPHP: Create Values Missing.');
                }
            
                $query          = $this->buildQuery('create',$args);
                $queryResult    = mysqli_query($this->connection, $query);

                if (!$queryResult){
                    $response['hasError']       = true;
                    $response['errorMessage']   = mysqli_error($this->connection);
                }

            }else{

            }

            return (object) $response;
            
        }

        public function delete($args){

            $response   = array(
                'hasError'      =>  false,
                'content'       =>  null,
                'errorMessage'  =>  '',
            );

            if (!is_array($args)){
                if (!empty($args)){
                    throw new \Exception ('YSPHP: Update Query Missing.');
                }
            }else{
                if (!isset($args["table"]) || empty($args["table"])){
                    throw new \Exception ('YSPHP: Missing Delete Query Parameter: Table');
                }

                $query          = $this->buildQuery('delete',$args);
                $queryResult    = mysqli_query($this->connection, $query);

                if (!$queryResult){
                    $response['hasError']       = true;
                    $response['errorMessage']   = mysqli_error($this->connection);
                }
            }

            return (object) $response;
        }

        public function buildQuery($qryType,$args){
            $query = '';
            switch ($qryType){
                case 'update':
                    $updateQry  = [];
                    foreach ($args['set'] as $setValues){
                        $isString = isset($setValues['isString']) ? $setValues['isString']:true;
                        $updateQry [] = $isString 
                                    ? " `{$setValues['key']}` = '{$setValues['value']}' "
                                    : " `{$setValues['key']}` = {$setValues['value']} ";
                    }
                    $query      = sprintf('UPDATE %s SET %s ',$args['table'],implode(',' ,$updateQry));
                    $query      .= isset($args['where']) ? $this->buildWhereQry($args['where']):'';
                    $query      .= isset($args['between']) ? $this->buildBetweenQry($args['between']):'';
                    break;
                case "count":
                    $query      = sprintf('SELECT %s FROM %s ',"COUNT({$args['key']}) as c"," {$args['table']} ");
                    $query      .= isset($args['where']) ? $this->buildWhereQry($args['where']):'';
                    $query      .= isset($args['between']) ? $this->buildBetweenQry($args['between']):'';
                    break;
                case 'insert':
                    break;
                case 'delete':
                    $query      = sprintf('DELETE FROM %s '," {$args['table']} ");
                    $query      .= isset($args['where']) ? $this->buildWhereQry($args['where']):'';
                    $query      .= isset($args['between']) ? $this->buildBetweenQry($args['between']):'';
                    break;
                case 'create':
                    $query          = "CREATE TABLE `{$args['table']}` ";
                    $primary_key    = '';
                    $collation      = isset($args['collation']) ?  $this->getCollationQry($args['collation']): $this->getCollationQry('utf8');
                    $query_ary      = [];
                    $index_ary      = [];
                    foreach ($args['value'] as $value){

                        if (isset($value['primary']) && $value['primary']){
                            $primary_key = $value['name'];
                        }
                        if (!isset($value['type'])){
                            throw new \Exception ("YSPHP: Create Value '{$value['name']}' Missing Type.");
                        }

                        $length     = isset($value['length']) ? "({$value['length']})" : '';
                        $type       = strtoupper($value["type"]);
                        $length     = empty($length) ? $this->getDefaultLength($type):$length;
                        $onupdate   = isset($value['onUpdate']) ? " on update {$value['onUpdate']} ":'';
                        $null       = !isset($value['null']) || !$value['null'] ? ' NOT NULL ':' NULL ';
                        $ai         = isset($value['ai']) && $value['ai'] ? ' AUTO_INCREMENT ':'';
                        $default    = isset($value['default']) && !empty($value['default']) ? (' DEFAULT ' . $this->getDefaultValue( $type,$value['default'] ) ):'';

                        if (isset($value['index'])){
                            $index_ary[] = 'INDEX ' . ( empty($value['index']) ? $value['name'] . '_index':$value['index'] ) . " (`{$value['name']}`)";
                        }
                        $cl = $type === 'VARCHAR' || $type === 'TEXT' ? ' CHARACTER SET ' . $collation:'';
                        $query_ary[] = " `{$value['name']}` $type$length $ai $cl $onupdate $null $default ";
                    }

                    if (empty($primary_key)){
                        throw new \Exception ('YSPHP: Create Value Missing: Primary Key.');
                    }
                    $engine     = !isset($args['engine']) ? ' ENGINE = InnoDB;':" ENGINE = {$args['engine']}";
                    $indexes    = sizeof($index_ary) !== 0 ? ',' . implode(',',$index_ary):'';
                    $query      .= sprintf(' (%s, PRIMARY KEY (`%s`) %s)%s', implode(',',$query_ary), $primary_key, $indexes, $engine);
                default:

            }
            return $query;
        }

        private function getCollationQry($cl){
            switch ($cl){
                case 'utf8':
                    return ' utf8 COLLATE utf8_general_ci ';
                    break;
                default:
            }
        }

        private function getDefaultValue($type,$default){

            $df = '';

            switch (strtoupper($default)){
                case 'NULL':
                    $df = 'NULL';
                    break;
                case 'CURRENT_TIMESTAMP':
                    $df = 'CURRENT_TIMESTAMP';
                    break;
                default:
                    $df = $this->isFieldNumber($type) ? "{$default}":"'{$default}'";
            }

            return $df;
        }

        private function isFieldNumber($type){
            $intFields = array(
                'INT','TINYINT','SMALLINT','MEDIUMINT','BIGINT','DECIMAL','FLOAT','DOUBLE','REAL'
            );
            return in_array($type,$intFields);
        }

        private function getDefaultLength($type){
            $length = '';
            switch ($type){
                case 'VARCHAR':
                    $length = '(64)';
                    break;
                case 'INT':
                    $length = '(11)';
                    break;
            }

            return $length;
        }

        private function buildWhereQry($whereArgs){
            $qry = 'WHERE ';

            if (is_array($whereArgs)){
                if (!$whereArgs || sizeof($whereArgs) === 0){
                    throw new \Exception ('YSPHP: Mysql Query Missing Where Conditions');
                }

                $whereConditions = [];
                foreach ($whereArgs as $whereArg){
                    $isString = isset($whereArg['isString']) ? $whereArg['isString']:true;
                    $whereValue = $whereArg['operator'] === 'LIKE' || $whereArg['operator'] === 'like' ? "%{$whereArg['value']}%":$whereArg['value'];
                    $whereConditions[] = $isString 
                                ? " `{$whereArg['key']}` {$whereArg['operator']} '$whereValue' " 
                                : " `{$whereArg['key']}` {$whereArg['operator']} $whereValue ";
                }
                $qry .= implode('AND', $whereConditions);
                
                
            }else{
                if (empty($whereArgs)){
                    throw new \Exception ('YSPHP: Mysql Query Missing Where Conditions');
                }

                $qry = "WHERE $whereArgs ";
            }
            return $qry;
        }

        private function speicalWhereConditions($value){

            switch ($value){
                case 'NOW':
                    $newValue = 'NOW()';
                    break;
                case 'TIME':
                    break;
                default:
                    $newValue = $value;
            }

            return $newValue;
        }

        private function buildBetweenQry($betweenArgs){

        }

    }

?>