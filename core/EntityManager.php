<?php
class EntityManager {
    protected $connection;
    protected $class_name;

    protected $table;
    protected $primary_key;
    protected $is_primary_key_composite;
    protected $table_description;
    protected $guardad_fields;

    private $_id;
    private $untouched;
    private $date_time_properties;

    const DB_DATE_FORMAT = 'Y-m-d';
    const DATE_FORMAT = 'd/m/Y';
    const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';
    const DATETIME_FORMAT = 'd/m/Y H:i:s';

    public $built;

    public function __construct($options = array()) {
        
        $this->_id = null;
        $this->class_name = get_class($this);
        
        if (!isset($this->table))
            $this->table = self::getTablenameFromClass($this->class_name);

        if (!isset($this->primary_key))
            $this->primary_key = $this->table.'_id';
        else {
            if (is_array($this->primary_key))
                $this->is_primary_key_composite = true;
            else
                $this->is_primary_key_composite = false;
        } 


        $this->guarded_fields = array();
        $this->untouched = array();
        $this->date_time_properties = array();
        $this->built = true;

        if (!isset($options['connection']) || $options['connection'] !== false) {
            
            if (isset($options['table_description']) && !is_null($options['table_description']))
                $this->table_description = $options['table_description'];
            else
                $this->table_description = null;

            if (!isset($options['find_action']) || is_null($options['find_action']))
            {
                $options['find_action'] = null;
                $options['find_value'] = null; 
            }

            try {
                if (!isset($options['connection']) || is_null($options['connection']))
                    $this->connection = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' .  DB_DATABASE, 
                        DB_USERNAME, DB_PASSWORD
                    );
                else
                    $this->connection = $options['connection'];
                if ($this->class_name !== 'EntityManager' ) {
                    $this->built = $this->loadFields($options['find_action'], $options['find_value']);
                }
            } catch (PDOException $e) {
                throw new ErrorException('Failed to connect to the database or load fields: ' . $e->getMessage());
            }
        }
    }

    public static function all($connection = null)
    {
        if (is_null($connection))
            try {
                $connection = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' .  DB_DATABASE, 
                    DB_USERNAME, DB_PASSWORD
                );
            } catch (PDOException $e) {
                throw new ErrorException('Failed to connect to the database or load fields: ' . $e->getMessage());
            }
        

        $instances = array();
        $find = new static(array('connection' => $connection));
        $values = $find->_select();
        $table_description = $find->getTableDescription();

        foreach($values as $row)
            array_push($instances, new static(array(
                'connection' => $connection, 
                'find_action' => 'where', 
                'find_value' => $row, 
                'table_description' => $table_description,
            )));

        return Collection::fromArray($instances);
    }

    public static function findById($id, $connection = null) 
    {
        $entity = new static(array(
            'connection' => $connection, 
            'find_action' => 'find_by_id',
            'find_value' => $id,
        ));

        if (!$entity->built)
            return null;
        else
            return $entity;
    }

    public static function findByIdOrFail($id, $connection = null)
    {
        $entity = self::findById($id, $connection);

        if (is_null($entity))
            throw new ErrorException("Cannot find entity by id");

        return $entity;
    }

    public static function where(array $conditions, $connection = null)
    {
        if (is_null($connection))
            try {
                $connection = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' .  DB_DATABASE, 
                    DB_USERNAME, DB_PASSWORD
                );
            } catch (PDOException $e) {
                throw new ErrorException('Failed to connect to the database or load fields: ' . $e->getMessage());
            }
        

        $instances = array();
        $find = new static(array('connection' => $connection));
        $values = $find->_select(array('where' => $conditions));
        $table_description = $find->getTableDescription();

        foreach($values as $row)
            array_push($instances, new static(array(
                'connection' => $connection, 
                'find_action' => 'where', 
                'find_value' => $row, 
                'table_description' => $table_description,
            )));

        return Collection::fromArray($instances);
    }

    public static function whereRaw($conditions, $params = array(), $connection = null)
    {
        if (is_null($connection))
            try {
                $connection = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' .  DB_DATABASE, 
                    DB_USERNAME, DB_PASSWORD
                );
            } catch (PDOException $e) {
                throw new ErrorException('Failed to connect to the database or load fields: ' . $e->getMessage());
            }
        

        $instances = array();
        $find = new static(array('connection' => $connection));
        $values = $find->_select(array('where_raw' => $conditions, 'where_params' => $params));
        $table_description = $find->getTableDescription();

        foreach($values as $row)
            array_push($instances, new static(array(
                'connection' => $connection, 
                'find_action' => 'where', 
                'find_value' => $row, 
                'table_description' => $table_description,
            )));

        return Collection::fromArray($instances);
    }


    public static function allWith()
    {
        throw new BadMethodCallException('Not yet implemented.');
    }

    public static function findByIdWith() 
    {
        throw new BadMethodCallException('Not yet implemented.');
    }

    public static function whereWith()
    {
        throw new BadMethodCallException('Not yet implemented.');
    }

    public static function _concatenateRelation(array $conditions, $with, $connection = null)
    {
        if (is_null($connection)) {
            try {
                $connection = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' .  DB_DATABASE, 
                    DB_USERNAME, DB_PASSWORD
                );
            } catch (PDOException $e) {
                throw new ErrorException('Failed to connect to the database or load fields: ' . $e->getMessage());
            }
        }
        
        $instances = array();
        $table_fields = array();
        $find = new static(array('connection' => $connection));
        $table = $find->getTable();
        $table_description = $find->getTableDescription();

        foreach ($table_description as $description)
            $table_fields[$table.'.'.$description['Field']] = $description['Field'];

        $values = $find->_select(array(
            'fields' => $table_fields,
            'where' => $conditions, 
            'tables_join' => $with,
        ));

        foreach($values as $row)
            array_push($instances, new static(array(
                'connection' => $connection,
                'find_action' => 'where',
                'find_value' => $row,
                'table_description' => $table_description,
            )));

        return Collection::fromArray($instances);
    }


    public static function create($values, $connection = null)
    {
        $entity_manager = new static(array('connection' => $connection));
        return $entity_manager->update($values);
    }

    public function update($values)
    {
        foreach ($values as $field => $value)
            $this->$field = $value;
        $this->save();
        return $this;
    }

    public function assert($model, $ids = array())
    {
        $table_relationed = new $model(array('connection' => false));
        $pivot_table = $this->table.'_to_'.$table_relationed->getTable();

        if ($this->is_primary_key_composite)
            $primary_key_array = $this->_id;
        else
            $primary_key_array = array($this->primary_key => $this->_id);

        $this->_delete(array(
            'table' => $pivot_table,
            'where' => $primary_key_array,
        ));

        if (sizeof($ids) > 0) 
            $this->assertAdd($model, $ids, $primary_key_array);

        return $this;
    }

    public function assertAdd($model, $ids, $primary_key_array = null)
    {
        $table_relationed = new $model(array('connection' => false));
        $pivot_table = $this->table.'_to_'.$table_relationed->getTable();
        $data = array();

        if (is_null($primary_key_array)) {
            if ($this->is_primary_key_composite)
                $primary_key_array = $this->_id;
            else
                $primary_key_array = array($this->primary_key => $this->_id);
        }

        if (!$table_relationed->hasCompositePrimaryKey())
            foreach ($ids as $id)
                array_push($data, array_merge($primary_key_array, array($table_relationed->primary_key => $id)));
        else
            foreach ($ids as $id)
                array_push($data, array_merge($primary_key_array, array_combine($table_relationed->primary_key, $id)));


        $this->_insert($data, array(
            'table' => $pivot_table,
        ));

        return $this;
    }

    public static function destroy($id, $connection = null)
    {
        $entity = self::findById(array('connection' => $connection));
        if (!is_null($entity))
            $entity->delete();
    }

    public function delete()
    {
        if ($this->is_primary_key_composite)
            $primary_key_array = $this->_id;
        else
            $primary_key_array = array($this->primary_key => $this->_id);

        $this->_delete(array('where' => $primary_key_array));
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
            throw new ErrorException("Failed to perform custom retrieve query (SQL:$query) -> ". $e->getMessager());
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

    protected function _insert($data, $options = array())
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
            $table = $this->table;

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
                if ($this->is_primary_key_composite) {
                    foreach($this->primary_key as $field)
                        $this->_id[$field] = $data[$field];
                }
                else
                    $this->_id = $this->connection->lastInsertId();
            }
            
        } catch (PDOException $e) {
            throw new ErrorException('Failed to perform insertion query (table:'.$table.') > ' . $e->getMessage());
        }

        if (!$is_multi_insert)
            $this->id = $this->_id;
    }

    public function save() 
    {
        $response = false;

        if (is_null($this->_id) || ($this->is_primary_key_composite && is_null(reset($this->_id)))) {
            
            $attributes = array();
            $test_guarded_fields = sizeof($this->guarded_fields) > 0;
            $writable_fields = array_keys($this->untouched);
            
            if ($test_guarded_fields)
                foreach($writable_fields as $index => $field)
                    if (in_array($field, $this->guarded_fields, true))
                        unset($writable_fields[$index]);     

            if ($this->is_primary_key_composite && !is_null(reset($this->id))) {
                foreach($this->id as $key_field => $key)
                    $attributes[$key_field] = $key;
            } else if ($this->is_primary_key_composite)
                $writable_fields = array_merge($writable_fields, $this->primary_key);

            foreach ($writable_fields as $field)
                if ($this->isDateTimeProperty($field)) {
                    $attributes[$field] = $this->$field->format(self::DB_DATETIME_FORMAT);
                } else
                    $attributes[$field] = $this->$field;
            
            $response = $this->_insert($attributes);

        } else {
            
            $dirty_fields = array();
            $test_guarded_fields = sizeof($this->guarded_fields) > 0;
            
            $writable_fields_defaults = $this->untouched;

            if ($test_guarded_fields) {
                foreach(array_merge($this->guarded_fields, $this->primary_key) as $guarded_field)
                    if (isset($writable_fields_defaults[$guarded_field]))
                        unset($writable_fields_defaults[$guarded_field]);
            }

            foreach($writable_fields_defaults as $field => $default_value)
                if ($this->isDateTimeProperty($field)) {
                    $time = $this->$field->format(self::DB_DATETIME_FORMAT);
                    if ($time !== $default_value)
                        $dirty_fields[$field] = $time;
                } else
                    if ($this->$field !== $default_value)
                        $dirty_fields[$field] = $this->$field;

            if (sizeof($dirty_fields) > 0)
                $response = $this->_update($dirty_fields);

        }

        return $response;

    }

    protected function _update($data, $options = array())
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
            $table = $this->table;

        $is_multi_update = is_array(reset($data));

        if ($is_multi_update)
            foreach($data as $index => $object)
                $this->_update($object, $options['where'][$index]);
        else {
            $query = 'UPDATE '.$table. ' SET ';
            $params = array();

            foreach($data as $field => $value) {
                $params[':__UPDATE__'.$field] = $value;
                $query .= '`'.$field.'` = :__UPDATE__'.$field.',';
            }
            $query = rtrim($query, ',');

            if (!isset($options['where_raw'])) {
                if (!isset($options['where']))
                    if ($this->is_primary_key_composite) {
                        $first = true;
                        $options['where'] = array();
                        foreach($this->_id as $field_key => $key) {
                            if ($first) {
                                $first = false;
                                array_push($options['where'], array($field_key => $key));
                            } else
                                array_push($options['where'], array('AND' => array($field_key => $key)));
                        }
                    } else
                        $options['where'] = array($this->primary_key => $this->_id);

                $where = $this->getWhereQueryPiece($options['where']);
                $params = array_merge($params, $where['params']);
                $query .= $where['query'];
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
                return $statement->rowCount();
            } catch (PDOException $e) {
                throw new ErrorException('Failed to perform update query (table:'.$table.') > ' . $e->getMessage());
            } 
        }
    }

    protected function _select($options = array())
    {
        if (!is_array($options))
            throw new InvalidArgumentException('invalid $options array [valid arguments=table, fields, tables_join, where, group_by, order_by, limit]');

        if (isset($options['table'])) {
            if(!is_string($options['table']))
                throw new InvalidArgumentException('$option "table" argument must be a string');
            $table = $options['table'];
        } else 
            $table = $this->table;
    
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

    protected function _delete($options = array())
    {
        if (!is_array($options))
            throw new InvalidArgumentException('invalid $options array [valid arguments=table, fields, tables_join, where, group_by, order_by, limit]');

        if (isset($options['table'])) {
            if(!is_string($options['table']))
                throw new InvalidArgumentException('$option "table" argument must be a string');
            $table = $options['table'];
        } else 
            $table = $this->table;

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
            return $statement->rowCount();
        } catch (PDOException $e) {
            throw new ErrorException('Failed to perform deletion query > ' . $e->getMessage());
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

    private function loadFields($find_action, $value) {

        if (!is_null($find_action)) {
            if ($find_action === 'find_by_id') {
                if ($this->is_primary_key_composite) {
                    $first = true;
                    $primary_key_array = array();
                    foreach($value as $key => $id)
                        if ($first) {
                            $first = false;
                            array_push($primary_key_array, array($key => $id));
                        } else
                            array_push($primary_key_array, array('AND' => array($key => $id)));
                } else
                    $primary_key_array = array($this->primary_key => $value);

                $row = $this->_select(array('where' => $primary_key_array, 'limit' => 1));
                if (sizeof($row) > 0) 
                    $row = $row[0];
                else
                    return false;
            } else if ($find_action === 'where') {
                $row = $value;
            }
        }

        foreach($this->getTableDescription() as $description) {
            if ($description['Key'] === 'PRI') {
                $id = !is_null($find_action) ? intval($row[$description['Field']]) : null;
                if ($this->is_primary_key_composite) {
                    if (!isset($this->id))
                        $this->id = array();
                    $this->id[$description['Field']] = $id; 
                    $this->_id[$description['Field']] = $id;
                } else {    
                    $this->id = $id; 
                    $this->_id = $id;
                }
            } else {
                $field_type = preg_replace('/\([^)]*\)/', '', $description['Type']);
                $value = $this->parseType($field_type, !is_null($find_action) ? $row[$description['Field']] : $description['Default']);
                if (self::isTimeField($field_type)) {
                    array_push($this->date_time_properties, $description['Field']); 
                    $this->untouched[$description['Field']] = $value->format(self::DB_DATETIME_FORMAT);
                } else
                    $this->untouched[$description['Field']] = $value;
                $this->{$description['Field']} = $value;
            }
        }

        return true;
    }

    public function getTableDescription() 
    {
        if (is_null($this->table_description)) {
            $statement = $this->connection->prepare('DESCRIBE '.$this->table);
            $statement->execute();
            $this->table_description = $statement->fetchAll(PDO::FETCH_ASSOC);
        } 
        return $this->table_description;
    }

    private static function parseType($type, $value) 
    {
        switch($type) {
            case "date":
                $parsed_value = DateTime::createFromFormat(self::DB_DATE_FORMAT, $value);
                $parsed_value = $parsed_value === false ? new DateTime() : $parsed_value;
                break;
            case "datetime":
            case "timestamp":
                $parsed_value = DateTime::createFromFormat(self::DB_DATETIME_FORMAT, $value);
                $parsed_value = $parsed_value === false ? new DateTime() : $parsed_value;
                break;
            case "tinyint":
                $parsed_value = filter_var($value, FILTER_VALIDATE_BOOLEAN);            
                break;
            case "float":
            case "decimal":
                $parsed_value = floatval($value);
                break;
            case "int":
                $parsed_value = intval($value);
                break;
            default:
                $parsed_value = $value;
        }
        return $parsed_value;
    }

    protected function getTable()
    {
        return $this->table;
    }

    public static function getTablename()
    {
        $entity = new static(array('connection' => false));
        return $entity->getTable();
    }

    public function hasCompositePrimaryKey()
    {
        return $this->is_primary_key_composite;
    }

    protected function hasOne($model, $primary_key = null)
    {
        if (!is_string($model))
            throw new ErrorException('$model argument must be a string');

        if (!is_string($primary_key) || !empty($primary_key))
            $primary_key = $this->primary_key;

        $model_table = call_user_func($model.'::getTablename');
        $this->$model_table = call_user_func_array($model.'::where', array(array($primary_key => $this->_id), $this->connection))->first();
        return $this;       
    } 

    protected function belongsTo($model, $primary_key = null)
    {
        if (!is_string($model))
            throw new ErrorException('$model argument must be a string');

        if (!is_string($primary_key) || !empty($primary_key))
            $primary_key = $this->primary_key;
        
        $model_table = call_user_func($model.'::getTablename');
        $this->$model_table = call_user_func_array($model.'::_concatenateRelation', array(array($primary_key => $this->_id), array($this->table), $this->connection))->first();
        return $this;
    }

    protected function hasMany($model, $primary_key = null)
    {
        if (!is_string($model))
            throw new ErrorException('$model argument must be a string');

        if (!is_string($primary_key) || !empty($primary_key))
            $primary_key = $this->primary_key;

        $model_table = call_user_func($model.'::getTablename');

        if (property_exists(new $model, $primary_key))
            $this->$model_table = call_user_func_array($model.'::where', array(array($primary_key => $this->_id), $this->connection));
        else
            $this->$model_table = call_user_func_array($model.'::_concatenateRelation', array(array($primary_key => $this->_id), array($this->table.'_to_'.$model_table), $this->connection));
        
        return $this;       
    }

    protected function belongsToMany($model, $primary_key = null)
    {
        if (!is_string($model))
            throw new ErrorException('$model argument must be a string');

        if (!is_string($primary_key) || !empty($primary_key))
            $primary_key = $this->primary_key;
        
        $model_table = call_user_func($model.'::getTablename');;
        if (property_exists($this, $primary_key))
            $this->$model_table = call_user_func_array($model.'::_concatenateRelation', array(array($primary_key => $this->_id), array($this->table), $this->connection));
        else
            $this->$model_table = call_user_func_array($model.'::_concatenateRelation', array(array($primary_key => $this->_id), array($model_table.'_to_'.$this->table), $this->connection));

        return $this;
    }

    private function isDateTimeProperty($property_name)
    {
        foreach($this->date_time_properties as $date_time_property)
            if ($property_name === $date_time_property)
                return true;
        return false;
    }

    private static function hasArrayInMultiarrayElement($array)
    {
        return (is_array(reset($array)) && is_array(reset(reset($array))));
    }

    private static function isAssociative($array)
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private static function getTablenameFromClass($class)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $class));
    }

    private static function isTimeField($field_type)
    {
        foreach (array('datetime', 'date', 'timestamp') as $time_type)
            if ($time_type === $field_type)
                return true;
        return false;
    }
}
