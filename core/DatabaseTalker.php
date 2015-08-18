<?php
class DatabaseTalker
{
    protected $connection;
    protected $main_table;

    public function __construct($options = array())
    {
        if (isset($options['connection']) && !is_null(isset($options['connection']))) {
            $this->connection = $options['connection'];
        } else {
            try {
                $this->connection = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' .  DB_DATABASE, 
                    DB_USERNAME, DB_PASSWORD
                );
            } catch (PDOException $e) {
                throw new Exception("Cannot stabilish connection to the database > " . $e->getMessage());
            }
        }

        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (isset($options['main_table']) && !is_null($options['main_table']))
            $this->main_table = $options['main_table'];
        else
            $this->main_table = false;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function select($options = array())
    {
        if (!is_array($options))
            throw new InvalidArgumentException('invalid $options array [valid arguments=table, fields, tables_join, where, group_by, order_by, limit]');

        if (isset($options['table'])) {
            if(!is_string($options['table']))
                throw new InvalidArgumentException('$option "table" argument must be a string');
            $table = $options['table'];
        } else
            if ($this->main_table !== false)
                $table = $this->main_table;
            else
                throw new InvalidArgumentException('There must a table to query (no $main_table or $table given)');

        $query = 'SELECT';

        if (!isset($options['fields']))
            $query .= ' * ';
        else if (!is_array($options['fields'])) 
            throw new InvalidArgumentException('$option argument "fields" must be an array or a mapping array [field => as_name]');
        else $query .= $this->getFieldsQueryPiece($options['fields']);

        $query .= ' FROM ' . $table;

        if (isset($options['tables_join'])) {
            if (!is_array($options['tables_join']))
                throw new InvalidArgumentException('
                    $options argument "tables_join" must be an array [table_join_1, table_join_2..], 
                    a mapping array [[table_join => table_join_id]..], 
                    an array mapping a array map [[table_join => [$table_join_jump => $tables_join_id]]..],
                    an array mapping and array map to a two sized array
                    [[table_join => [table_join_jump => [table_join_id, table_join_jump_id]]]..]
                    or an array mapping an array map that maps fields
                    [[table_join => [table_join_jump => [table_join_id_field1 => table_join_jump_field2]]..]

                ');
                $query .= $this->getJoinQueryPiece($options['tables_join'], $table);
        }

        if (!isset($options['where_raw'])) {
            if (isset($options['where'])) {
                $params = array();
                if (!is_array($options['where']))
                    throw new InvalidArgumentException('
                        $options argument "where" must be a mapping array [field => value]
                        or an array of mapping arrays (of conditional of comparison) and after the first argument, de second array must 
                        have a conditional mapping
                        [[field1 => value1], [field2 => [conditional ("AND" or "OR") => value2]..],
                        [[field1 => [comparison ("=", "<>", "LIKE") => value1]],
                        [field2 => [conditional ("AND" or "OR") => [comparison => value2]..]
                    ');
                $where = $this->getWhereQueryPiece($options['where']);
                $params = $where['params'];
                $query .= $where['query'];       
            }
        } else { 
            if (isset($options['where_params']))
                $params = $options['where_params'];
            else
                $params = array();
            $query .= ' WHERE '.$options['where_raw'];
        }

        foreach (array('group_by' => ' GROUP BY ' ,'order_by' => ' ORDER BY ') as $argument_index => $argument)
            if (isset($options[$argument_index])) {
                if (!is_string($options[$argument_index]) && !is_array($options[$argument_index]))
                    throw new InvalidArgumentException('$options "'.$argument_index.'" argument must be a string field or an array of string fields (order matters)');

            $query .= $argument;
                if (is_string($options[$argument_index]))
                    $query .= $options[$argument_index];
                else {
                    foreach ($options[$argument_index] as $field)
                        $query .= $field.',';
                    $query = rtrim($query, ',');
                }
            }

        if (isset($options['limit'])) {
            if (!is_numeric($options['limit']))
                throw new InvalidArgumentException('$options "limit" argument must be a numeric value (string or number)');       
            $query .= ' LIMIT '.$options['limit'];
        }

        try {
            $statement = $this->connection->prepare($query);

            if (isset($options['where']) || isset($options['where_params'])) {
                $statement->execute($params);
            }   
            else
                $statement->execute();
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }   catch (PDOException $e) {
            throw new ErrorException('Failed to perform select query (table:'.$table.') > ' . $e->getMessage());
        }
    }

    public function insert($data, $options = array())
    {

        if (!is_array($data) || self::hasArrayInMultiarrayElement($data))
            throw new InvalidArgumentException('$data parameter must be an mapping array [column => value] or an array of mapping arrays [i => [column => value]] ');

        if (!is_array($options))
            throw new InvalidArgumentException('invalid $options array [valid arguments=~]');

        if (isset($options['table'])) {
            if(!is_string($options['table']))
                throw new InvalidArgumentException('$option "table" argument must be a string');
            $table = $options['table'];
        } else 
            $table = $this->main_table;

        $response = array();

        $query = "INSERT INTO ". $table;

        $namevalues = array();
        $is_multi_insert = is_array(reset($data));

        if ($is_multi_insert) {

            $columns = array_keys($data[0]);                
            foreach ($data as $index => $element) {
                $namevalue = array(); 
                foreach ($columns as $field) 
                    array_push($namevalue, ':__INSERT__' . $index . '_' . $field);
                array_push($namevalues, $namevalue);
            }

        } else {

            $namevalue = array();
            $columns = array_keys($data);

            foreach ($columns as $field)
                array_push($namevalue, ':__INSERT__' . $field);
            array_push($namevalues, $namevalue);

        }

        $query .= "(`" . implode("`,`", $columns) . "`)" . " VALUES ";
        foreach ($namevalues as $namevalue) 
             $query .= "(" . implode(',', $namevalue ) . "),";
        $query = rtrim($query, ',');

        try {
            $statement = $this->connection->prepare($query);

            if ($is_multi_insert) {

                $array_combined = array();
                    foreach($namevalues as $index => $params)
                        $array_combined = array_merge($array_combined, array_combine($params, $data[$index]));

                $statement->execute($array_combined);

            } else {
                $statement->execute(array_combine($namevalues[0], $data));
            }
            
            if (!$is_multi_insert) {
                $response['last_inserted_id'] = $this->connection->lastInsertId();
            } else {
                $response['last_inserted_id'] = false;
                $response['is_multi_insert'] = true;    
            }
        } catch (PDOException $e) {
            throw new ErrorException('Failed to perform insertion query (table:'.$table.') > ' . $e->getMessage());
        }

        return $response;
    }

    public function update($data, $options = array())
    {
        if (!is_array($data) || self::hasArrayInMultiarrayElement($data))
            throw new InvalidArgumentException('$data parameter must be an mapping array [column => value] or an array of mapping arrays [i => [column => value]] ');

        if (!is_array($options))
            throw new InvalidArgumentException('invalid $options array [valid arguments=table,where,where_raw]');

        if (isset($options['table'])) {
            if(!is_string($options['table']))
                throw new InvalidArgumentException('$option "table" argument must be a string');
            $table = $options['table'];
        } else 
            $table = $this->main_table;

        $response = array();

        $is_multi_update = is_array(reset($data));

        if ($is_multi_update) {
            $response['is_multi_update'] = true;
            $response['updates'] = array();
            foreach($data as $index => $object)
                array_push($response['updates'], $this->update($object, $options['where'][$index]));
        } else {
            $query = 'UPDATE '.$table. ' SET ';
            $params = array();

            foreach($data as $field => $value) {
                $params[':__UPDATE__'.$field] = $value;
                $query .= '`'.$field.'` = :__UPDATE__'.$field.',';
            }
            $query = rtrim($query, ',');

            if (!isset($options['where_raw'])) {
                if (isset($options['where'])) {
                    $where = $this->getWhereQueryPiece($options['where']);
                    $params = array_merge($params, $where['params']);
                    $query .= $where['query'];
                }

            } else {
                if (isset($options['where_params']))
                    $params = $options['where_params'];
                else
                    $params = array();    
                $query .= ' WHERE '.$options['where_raw'];
            }

            try {
                $statement = $this->connection->prepare($query);
                $statement->execute($params);
                
                $response['rows_updated'] = $statement->rowCount();
                $response['was_specific'] = isset($options['where']) || isset($options['where_raw']);
            } catch (PDOException $e) {
                throw new ErrorException('Failed to perform update query (table:'.$table.') > ' . $e->getMessage());
            } 
        }
        return $response;
    }

    public function delete($options = array())
    {
        if (!is_array($options))
            throw new InvalidArgumentException('invalid $options array [valid arguments=table, fields, tables_join, where, group_by, order_by, limit]');

        if (isset($options['table'])) {
            if(!is_string($options['table']))
                throw new InvalidArgumentException('$option "table" argument must be a string');
            $table = $options['table'];
        } else 
            $table = $this->main_table;

        $response = array();

        $query = 'DELETE FROM '.$table;

        if (isset($options['where'])) {
            $where = $this->getWhereQueryPiece($options['where']);
            $params = $where['params'];
            $query .= $where['query'];
        } else 
            throw new ErroException('No "where" conditions defined for the query');

        try {
            $statement = $this->connection->prepare($query);
            $statement->execute($params);
            $response['rows_deleted'] = $statement->rowCount();
        } catch (PDOException $e) {
            throw new ErrorException('Failed to perform deletion query > ' . $e->getMessage());
        }

        return $response;    
    }

    public function retrieve($query, $data = null)
    {   
        try {
            $statement = $this->connection->prepare($query);
            if (!is_null($data))
                $statement->execute($data);
            else
                $statement->execute();
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            throw new ErrorException("Failed to perform custom retrieve query (SQL:$query) -> ". $e->getMessage());
        }
    }

    public function query($query, $data = null)
    {
        try {
            $statement = $this->connection->prepare($query);
            if (!is_null($data))
                $statement->execute($data);
            else
                $statement->execute();
        } catch(PDOException $e) {
            throw new ErrorException("Failed to perform custom query (SQL:$query) -> ". $e->getMessager());
        }
    }

    private function getFieldsQueryPiece($fields)
    {
        $query = '';
        foreach ($fields as $field)
            if (is_array($field))
                $query .= ' ' . key($field) . ' AS ' . reset($field) . ',';
            else
                $query .= ' ' . $field . ',';

        $query = rtrim($query, ',');
        return $query;
    }

    private function getJoinQueryPiece($tables_join, $table)
    {
        $query = '';
        foreach ($tables_join as $index => $table_join)
            if (!is_array($table_join)) 
                $query .= ' JOIN `' . $table_join . '` USING (' . $table . '_id)';
            else {
                $table_join_element = key($table_join);
                $table_join_column = reset($table_join);

                if (is_array($table_join_column)) {
                    if (self::isAssociative($table_join_column)) {
                        $first = true;
                        $query .= ' JOIN '.$table_join_element.' ON (';
                        foreach ($table_join_column as $table_join_column_condition => $table_join_column_jump_and_field) {
                            if ($first) {
                                $first = false;
                                $query .= '`'.$table_join_element.'`.`'.$table_join_column_condition.'` = `'.$table.'`.`'.$table_join_column_jump_and_field.'`';
                            } else
                                $query .= $table_join_column_condition.' `'.$table_join_element.'`.`'.key($table_join_column_jump_and_field).'` = `'.$table.'`.`'.reset($table_join_column_jump_and_field).'`';
                        }
                        $query .= ')';
                    } else 
                        $query .= ' JOIN `'.$table_join_element.'` ON (`'.$table_join_element.'`.`'.$table_join_column[1].'` = `'.$table.'`.`'.$table_join_column[0].'`';
                } else
                    $query .= ' JOIN `'.$table_join_element.'` ON (`'.$table_join_element.'`.`'.$table_join_column.'` = `'.$table.'`.`'.$table_join_column.'`';
            }
        return $query;
    }   

    private function getWhereQueryPiece($where)
    {
        $query = ' WHERE ';
        $params = array();
        if (sizeof($where) === 1)  {
            $value = reset($where);
            if (is_array($value)) {
                $field = key($where);
                $query .= '`'.$field.'` '.key($value).' :__WHERE__'.$field;
                $params[':__WHERE__'.$field] = reset($value);
            } else {
                $field = key($where);
                $query .= '`'.$field.'` = :__WHERE__'.$field;
                $params[':__WHERE__'.$field] = $value;
            }
        } else {
            $first = true;
            foreach ($where as $index => $clause) {                     
                if ($first) {
                    $first = false;
                    $field = key($clause);
                    $value = reset($clause);
                    if (is_array($value)) {                 
                        $query .= '`'.$field.'` '.key($value).' :__WHERE__'.$index.'_'.$field;
                        $params[':__WHERE__'.$index.'_'.$field] = reset($value);
                    } else {
                        $query .= '`'.$field.'` = :__WHERE__'.$index.'_'.$field;
                        $params[':__WHERE__'.$index.'_'.$field] = $value;
                    }
                } else {
                    $condition = key($clause);
                    $field_and_value = reset($clause);
                    $field = key($field_and_value);
                    $value = reset($field_and_value); 
                    if (is_array($value)) {
                        $query .= ' '.$condition.' `'.$field.'` '.key($value).':__WHERE__'.$index.'_'.$field;
                        $params[':__WHERE__'.$index.'_'.$field] = reset($value);
                    } else {
                        $query .= ' '.$condition.' `'.$field.'` = :__WHERE__'.$index.'_'.$field;
                        $params[':__WHERE__'.$index.'_'.$field] = $value;
                    }
                }
            }
        }
        return array('params' => $params, 'query' => $query);
    }

    private static function hasArrayInMultiarrayElement($array)
    {   
        $first = reset($array);
        return (is_array($first) && is_array(reset($first)));
    }

    private static function isAssociative($array)
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
