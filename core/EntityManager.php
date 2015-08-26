<?php

class EntityManager
{
    const DB_DATE_FORMAT = 'Y-m-d';
    const DATE_FORMAT = 'd/m/Y';
    const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';
    const DATETIME_FORMAT = 'd/m/Y H:i:s';
    public $built;
    protected $db_talker;
    protected $class_name;
    protected $table;
    protected $primary_key;
    protected $is_primary_key_composite;
    protected $table_description;
    protected $guarded_fields;
    protected $_columns;
    private $_id;
    private $untouched;
    private $date_time_properties;

    public function __construct($options = array())
    {

        $this->_id = null;
        $this->class_name = get_class($this);

        if (!isset($this->table)) {
            $this->table = self::getTableNameFromClass($this->class_name);
        }

        if (!isset($this->primary_key)) {
            $this->primary_key = $this->table . '_id';
        } else {
            if (is_array($this->primary_key)) {
                $this->is_primary_key_composite = true;
            } else {
                $this->is_primary_key_composite = false;
            }
        }

        $this->_columns = array();

        $this->guarded_fields = array();
        $this->untouched = array();
        $this->date_time_properties = array();
        $this->built = true;

        if (!isset($options['connection']) || $options['connection'] !== false) {

            if (isset($options['table_description']) && !is_null($options['table_description'])) {
                $this->table_description = $options['table_description'];
            } else {
                $this->table_description = null;
            }

            if (!isset($options['find_action']) || is_null($options['find_action'])) {
                $options['find_action'] = null;
                $options['find_value'] = null;
            }

            $this->db_talker = new DatabaseTalker(array(
                'connection' => isset($options['connection']) ? $options['connection'] : null,
                'main_table' => $this->table,
            ));

            $this->built = $this->loadFields($options['find_action'], $options['find_value']);
        }
    }

    private static function getTableNameFromClass($class)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $class));
    }

    private function loadFields($find_action, $value)
    {

        if (!is_null($find_action)) {
            if ($find_action === 'find_by_id') {
                if ($this->is_primary_key_composite) {
                    $first = true;
                    $primary_key_array = array();
                    foreach ($value as $key => $id) {
                        if ($first) {
                            $first = false;
                            array_push($primary_key_array, array($key => $id));
                        } else {
                            array_push($primary_key_array, array('AND' => array($key => $id)));
                        }
                    }
                } else {
                    $primary_key_array = array($this->primary_key => $value);
                }

                $row = $this->db_talker->select(array('where' => $primary_key_array, 'limit' => 1));

                if (sizeof($row) > 0) {
                    $row = $row[0];
                }

            } else {
                if ($find_action === 'where') {
                    $row = $value;
                }
            }
        }

        if (!isset($row) || !is_array($row)) {
            return false;
        }

        foreach ($this->getTableDescription() as $description) {
            if ($description['Key'] === 'PRI') {
                $id = !is_null($find_action) ? intval($row[$description['Field']]) : null;
                if ($this->is_primary_key_composite) {
                    if (!isset($this->id)) {
                        $this->_columns['id'] = array();
                    }
                    $this->id[$description['Field']] = $id;
                    $this->_id[$description['Field']] = $id;
                } else {
                    $this->id = $id;
                    $this->_id = $id;
                }
            } else {
                $field_type = preg_replace('/\([^)]*\)/', '', $description['Type']);
                $value = $this->parseType($field_type,
                    !is_null($find_action) ? $row[$description['Field']] : $description['Default']);
                if (self::isTimeField($field_type)) {
                    array_push($this->date_time_properties, $description['Field']);
                    $this->untouched[$description['Field']] = $value->format(self::DB_DATETIME_FORMAT);
                } else {
                    $this->untouched[$description['Field']] = $value;
                }
                $this->_columns[$description['Field']] = $value;
            }
        }

        return true;
    }

    public function getTableDescription()
    {
        if (is_null($this->table_description)) {
            $this->table_description = $this->db_talker->retrieve('DESCRIBE ' . $this->table);
        }
        return $this->table_description;
    }

    private static function parseType($type, $value)
    {
        switch ($type) {
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

    private static function isTimeField($field_type)
    {
        foreach (array('datetime', 'date', 'timestamp') as $time_type) {
            if ($time_type === $field_type) {
                return true;
            }
        }
        return false;
    }

    public static function all($connection = null)
    {
        $instances = array();
        $table = self::getTableName();

        if (is_null($connection)) {
            $db_talker = new DatabaseTalker(array('connection' => $connection, 'main_table' => $table));
        } else {
            $db_talker = new DatabaseTalker(array('main_table' => $table));
            $connection = $db_talker->getConnection();
        }

        $table_description = $db_talker->retrieve('DESCRIBE ' . $table);
        $model = new ReflectionClass(get_called_class());

        foreach ($db_talker->select() as $row) {
            array_push($instances, $model->newInstance(array(
                'connection' => $connection,
                'find_action' => 'where',
                'find_value' => $row,
                'table_description' => $table_description,
            )));
        }

        return Collection::fromArray($instances);
    }

    public static function getTableName()
    {
        $model = new ReflectionClass(get_called_class());
        $entity = $model->newInstance(array('connection' => false));
        return $entity->getTable();
    }

    protected function getTable()
    {
        return $this->table;
    }

    public static function findByIdOrFail($id, $connection = null)
    {
        $entity = self::findById($id, $connection);

        if (is_null($entity)) {
            throw new ErrorException("Cannot find entity by id");
        }

        return $entity;
    }

    public static function findById($id, $connection = null)
    {
        $model = new ReflectionClass(get_called_class());
        $entity = $model->newInstance(array(
            'connection' => $connection,
            'find_action' => 'find_by_id',
            'find_value' => $id,
        ));

        if ($entity->built) {
            return $entity;
        } else {
            return null;
        }
    }

    public static function where(array $conditions, $connection = null)
    {
        $instances = array();
        $table = self::getTableName();

        if (is_null($connection)) {
            $db_talker = new DatabaseTalker(array('connection' => $connection, 'main_table' => $table));
        } else {
            $db_talker = new DatabaseTalker(array('main_table' => $table));
            $connection = $db_talker->getConnection();
        }
        $table_description = $db_talker->retrieve('DESCRIBE ' . $table);

        $model = new ReflectionClass(get_called_class());

        foreach ($db_talker->select(array('where' => $conditions)) as $row) {
            array_push($instances, $model->newInstance(array(
                'connection' => $connection,
                'find_action' => 'where',
                'find_value' => $row,
                'table_description' => $table_description,
            )));
        }

        return Collection::fromArray($instances);
    }

    public static function whereRaw($conditions, $params = array(), $connection = null)
    {
        $instances = array();
        $table = self::getTableName();

        if (is_null($connection)) {
            $db_talker = new DatabaseTalker(array('connection' => $connection, 'main_table' => $table));
        } else {
            $db_talker = new DatabaseTalker(array('main_table' => $table));
            $connection = $db_talker->getConnection();
        }

        $table_description = $db_talker->retrieve('DESCRIBE ' . $table);
        $model = new ReflectionClass(get_called_class());

        foreach ($db_talker->select(array('where_raw' => $conditions, 'where_params' => $params)) as $row) {
            array_push($instances, $model->newInstance(array(
                'connection' => $connection,
                'find_action' => 'where',
                'find_value' => $row,
                'table_description' => $table_description,
            )));
        }


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
        $instances = array();
        $table_fields = array();
        $table = self::getTableName();

        if (is_null($connection)) {
            $db_talker = new DatabaseTalker(array('connection' => $connection, 'main_table' => $table));
        } else {
            $db_talker = new DatabaseTalker(array('main_table' => $table));
            $connection = $db_talker->getConnection();
        }

        $table_description = $db_talker->retrieve('DESCRIBE ' . $table);

        foreach ($table_description as $description) {
            $table_fields[$table . '.' . $description['Field']] = $description['Field'];
        }

        foreach ($db_talker->select(array(
            'fields' => $table_fields,
            'where' => $conditions,
            'tables_join' => $with,
        )) as $row) {
            array_push($instances, new static(array(
                'connection' => $connection,
                'find_action' => 'where',
                'find_value' => $row,
                'table_description' => $table_description,
            )));
        }

        return Collection::fromArray($instances);
    }

    public static function create($values, $connection = null)
    {
        $model = new ReflectionClass(get_called_class());
        $entity_manager = $model->newInstance(array('connection' => $connection));
        return $entity_manager->update($values);
    }

    public static function destroy($id, $connection = null)
    {
        $db_talker = new DatabaseTalker(array('connection' => $connection, 'main_table' => self::getTableName()));
        if (is_array($id)) {
            $delete_where = array();
            $first = true;
            foreach ($id as $field => $key) {
                if ($first) {
                    $first = false;
                    array_push($delete_where, array($field => $key));
                } else {
                    array_push($delete_where, array('AND' => array($field => $key)));
                }
            }
            $response = $db_talker->delete(array('where' => $delete_where));
        } else {
            $response = $db_talker->delete(array('where' => array(self::getPrimaryKey() => $id)));
        }
        return $response;
    }

    public function getPrimaryKey()
    {
        return $this->primary_key;
    }

    public static function getPrimaryKeyFields()
    {
        $entity = new static(array('connection' => false));
        return $entity->getPrimaryKey();
    }

    public function __get($property)
    {
        if (isset($this->_columns[$property])) {
            return $this->_columns[$property];
        } else {
            return null;
        }
    }

    public function __set($property, $value)
    {
        if (isset($this->_columns[$property])) {
            $this->_columns[$property] = $value;
        }
    }

    public function update($values)
    {
        foreach ($values as $field => $value) {
            $this->$field = $value;
        }
        $this->save();
        return $this;
    }

    public function save()
    {
        $response = false;

        if (is_null($this->_id) || ($this->is_primary_key_composite && is_null(reset($this->_id)))) {

            $attributes = array();
            $test_guarded_fields = sizeof($this->guarded_fields) > 0;
            $writable_fields = array_keys($this->untouched);

            if ($test_guarded_fields) {
                foreach ($writable_fields as $index => $field) {
                    if (in_array($field, $this->guarded_fields, true)) {
                        unset($writable_fields[$index]);
                    }
                }
            }

            if ($this->is_primary_key_composite && !is_null(reset($this->id))) {
                foreach ($this->id as $key_field => $key) {
                    $attributes[$key_field] = $key;
                }
            } else {
                if ($this->is_primary_key_composite) {
                    $writable_fields = array_merge($writable_fields, $this->primary_key);
                }
            }

            foreach ($writable_fields as $field) {
                if ($this->isDateTimeProperty($field)) {
                    $attributes[$field] = $this->$field->format(self::DB_DATETIME_FORMAT);
                } else {
                    $attributes[$field] = $this->$field;
                }
            }

            $response = $this->db_talker->insert($attributes);

            if (!$this->is_primary_key_composite) {
                $this->_id = $response['last_inserted_id'];
                $this->id = $response['last_inserted_id'];
            } else {
                if (is_array($this->primary_key)) {
                    foreach ($this->primary_key as $primary_key_field) {
                        $this->_id[$primary_key_field] = $attributes[$primary_key_field];
                    }
                    $this->id = $this->_id;
                }
            }

        } else {

            $dirty_fields = array();
            $test_guarded_fields = sizeof($this->guarded_fields) > 0;

            $writable_fields_defaults = $this->untouched;

            if ($test_guarded_fields) {
                foreach (array_merge($this->guarded_fields, $this->primary_key) as $guarded_field) {
                    if (isset($writable_fields_defaults[$guarded_field])) {
                        unset($writable_fields_defaults[$guarded_field]);
                    }
                }
            }

            foreach ($writable_fields_defaults as $field => $default_value) {
                if ($this->isDateTimeProperty($field)) {
                    $time = $this->$field->format(self::DB_DATETIME_FORMAT);
                    if ($time !== $default_value) {
                        $dirty_fields[$field] = $time;
                    }
                } else {
                    if ($this->$field !== $default_value) {
                        $dirty_fields[$field] = $this->$field;
                    }
                }
            }

            $primary_key_where = array();

            if ($this->is_primary_key_composite) {
                $first = true;
                foreach ($this->_id as $field => $key) {
                    if ($first) {
                        $first = false;
                        array_push($primary_key_where, array($field => $key));
                    } else {
                        array_push($primary_key_where, array('AND' => array($field => $key)));
                    }

                }
            } else {
                $primary_key_where[$this->primary_key] = $this->_id;
            }

            if (sizeof($dirty_fields) > 0) {
                $response = $this->db_talker->update($dirty_fields, array('where' => $primary_key_where));
            }
        }

        return $response;
    }

    private function isDateTimeProperty($property_name)
    {
        foreach ($this->date_time_properties as $date_time_property) {
            if ($property_name === $date_time_property) {
                return true;
            }
        }
        return false;
    }

    public function assert($model, $ids = array())
    {
        $model = new ReflectionClass($model);
        $table_relationed = $model->newInstance(array('connection' => false));
        $pivot_table = $this->table . '_to_' . $table_relationed->getTable();

        if ($this->is_primary_key_composite) {
            $primary_key_array = $this->_id;
        } else {
            $primary_key_array = array($this->primary_key => $this->_id);
        }

        $this->db_talker->delete(array(
            'table' => $pivot_table,
            'where' => $primary_key_array,
        ));

        if (sizeof($ids) > 0) {
            $this->assertAdd($model, $ids, $primary_key_array);
        }

        return $this;
    }

    public function assertAdd($model, $ids, $primary_key_array = null)
    {
        $model = new ReflectionClass($model);
        $table_relationed = $model->newInstance(array('connection' => false));
        $pivot_table = $this->table . '_to_' . $table_relationed->getTable();
        $data = array();

        if (is_null($primary_key_array)) {
            if ($this->is_primary_key_composite) {
                $primary_key_array = $this->_id;
            } else {
                $primary_key_array = array($this->primary_key => $this->_id);
            }
        }

        if (!$table_relationed->hasCompositePrimaryKey()) {
            foreach ($ids as $id) {
                array_push($data, array_merge($primary_key_array, array($table_relationed->primary_key => $id)));
            }
        } else {
            foreach ($ids as $id) {
                array_push($data, array_merge($primary_key_array, array_combine($table_relationed->primary_key, $id)));
            }
        }

        $this->db_talker->insert($data, array(
            'table' => $pivot_table,
        ));

        return $this;
    }

    public function delete()
    {
        if ($this->is_primary_key_composite) {
            $first = true;
            $primary_key_array = array();
            foreach ($this->_id as $field => $key) {
                if ($first) {
                    $first = true;
                    array_push($primary_key_array, array($field => $key));
                } else {
                    array_push($primary_key_array, array('AND' => array($field => $key)));
                }
            }
        } else {
            $primary_key_array = array($this->primary_key => $this->_id);
        }

        $this->db_talker->delete(array('where' => $primary_key_array));
    }

    public function hasCompositePrimaryKey()
    {
        return $this->is_primary_key_composite;
    }

    protected function hasOne($model, $primary_key = null)
    {
        if (!is_string($model)) {
            throw new ErrorException('$model argument must be a string');
        }

        if (!is_string($primary_key) || !empty($primary_key)) {
            $primary_key = $this->primary_key;
        }

        $model_table = call_user_func($model . '::getTableName');
        $this->$model_table = call_user_func_array($model . '::where',
            array(array($primary_key => $this->_id), $this->db_talker->getConnection()))->first();
        return $this;
    }

    protected function belongsTo($model, $primary_key = null)
    {
        if (!is_string($model)) {
            throw new ErrorException('$model argument must be a string');
        }

        if (!is_string($primary_key) || !empty($primary_key)) {
            $primary_key = $this->primary_key;
        }

        $model_table = call_user_func($model . '::getTableName');
        $this->$model_table = call_user_func_array($model . '::_concatenateRelation',
            array(array($primary_key => $this->_id), array($this->table), $this->db_talker->getConnection()))->first();
        return $this;
    }

    protected function hasMany($model, $primary_key = null)
    {
        if (!is_string($model)) {
            throw new ErrorException('$model argument must be a string');
        }

        if (!is_string($primary_key) || !empty($primary_key)) {
            $primary_key = $this->primary_key;
        }

        $model = new ReflectionClass($model);
        $entity = $model->newInstance(array('connection' => $this->db_talker->getConnection()));
        $model_table = $entity->getTable();

        if (property_exists($entity, $primary_key) || ($entity->hasCompositePrimaryKey() && in_array($primary_key,
                    $entity->getPrimaryKey()))
        ) {
            $this->$model_table = call_user_func_array($model . '::where',
                array(array($primary_key => $this->_id), $this->db_talker->getConnection()));
        } else {
            $this->$model_table = call_user_func_array($model . '::_concatenateRelation', array(
                array($primary_key => $this->_id),
                array($this->table . '_to_' . $model_table),
                $this->db_talker->getConnection()
            ));
        }

        return $this;
    }

    protected function belongsToMany($model, $primary_key = null)
    {
        if (!is_string($model)) {
            throw new ErrorException('$model argument must be a string');
        }

        if (!is_string($primary_key) || !empty($primary_key)) {
            $primary_key = $this->primary_key;
        }

        $model_table = call_user_func($model . '::getTableName');

        if (property_exists($this, $primary_key) || ($this->is_primary_key_composite && in_array($primary_key,
                    $this->primary_key))
        ) {
            $this->$model_table = call_user_func_array($model . '::_concatenateRelation',
                array(array($primary_key => $this->_id), array($this->table), $this->db_talker->getConnection()));
        } else {
            $this->$model_table = call_user_func_array($model . '::_concatenateRelation', array(
                array($primary_key => $this->_id),
                array($model_table . '_to_' . $this->table),
                $this->db_talker->getConnection()
            ));
        }

        return $this;
    }
}
