<?php
	//require_once('config.php');

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

		public $built;

		public function __construct($options = array()) {
			
			$this->_id = null;
			$this->class_name = get_class($this);
			
			if (!isset($this->table))
				$this->table = self::get_tablename_from_class($this->class_name);

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

					$this->built = $this->load_fields($options['find_action'], $options['find_value']);
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
			$table_description = $find->get_table_description();

			foreach($values as $row)
				array_push($instances, new static(array(
					'connection' => $connection, 
					'find_action' => 'where', 
					'find_value' => $row, 
					'table_description' => $table_description,
				)));

			return Collection::from_array($instances);
		}

		public static function find_by_id($id, $connection = null) 
		{
			return new static(array(
				'connection' => $connection, 
				'find_action' => 'find_by_id',
				'find_value' => $id,
			));
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
			$table_description = $find->get_table_description();

			foreach($values as $row)
				array_push($instances, new static(array(
					'connection' => $connection, 
					'find_action' => 'where', 
					'find_value' => $row, 
					'table_description' => $table_description,
				)));

			return Collection::from_array($instances);
		}

		public static function all_with()
		{
			throw new BadMethodCallException('Not yet implemented.');
		}

		public static function find_by_id_with() 
		{
			throw new BadMethodCallException('Not yet implemented.');
		}

		public static function where_with()
		{
			throw new BadMethodCallException('Not yet implemented.');
		}

		public static function _concatenate_relation(array $conditions, $with, $connection = null)
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
			$table_fields = array();
			$find = new static(array('connection' => $connection));
			$table = $find->table();
			$table_description = $find->get_table_description();

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

			return Collection::from_array($instances);
		}


		public static function create($values, $connection = null)
		{
			$entity_manager = new static(array('connection' => $connection));
			return $entity_manager->update($values);
		}

		public function update($values)
		{
			$fields = array_keys($this->untouched);
			foreach($values as $field => $value)
				if (in_array($field, $fields, true))
					$this->$field = $value;
			$this->save();
			return $this;
		}

		public function assert($model, $ids)
		{
			$table_relationed = self::get_tablename_from_class($model);
			$pivot_table = $this->table.'_to_'.$table_relationed;

			if ($this->is_primary_key_composite)
				$primary_key_array = $this->_id;
			else
				$primary_key_array = array($this->primary_key => $this->_id);

			$this->_delete(array(
				'table' => $pivot_table,
				'where' => $primary_key_array,
			));

			$data = array();
			foreach ($ids as $id)
				array_push($data, array_merge($primary_key_array, array($table_relationed.'_id' => $id)));

			$this->_insert($data, array(
				'table' => $pivot_table,
			));
			return $this;
		}

		protected function _insert($data, $options = array())
		{

			if (!is_array($data) || self::has_array_in_multiarray_element($data))
				throw new ErrorException('$data parameter must be an mapping array [column => value] or an array of mapping arrays [i => [column => value]] ');

			if (!is_array($options))
				throw new ErrorException('invalid $options array [valid arguments=~]');

			if (isset($options['table'])) {
				if(!is_string($options['table']))
					throw new ErrorException('$option "table" argument must be a string');
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

				$columns = array_keys($data);
				$namevalue = array();

				foreach ($columns as $field)
					array_push($namevalue, ':__INSERT__' . $field);
				array_push($namevalues, $namevalue);

			}

			$query .= "(" . implode(",", $columns) . ")" . " VALUES ";
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

				} else
					$statement->execute(array_combine($namevalues[0], $data));

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
			if (is_null($this->_id)) {
				$attributes = array();
				$not_test_guarded_fields = sizeof($this->guarded_fields) === 0;
				foreach (array_keys($this->untouched) as $field)
					if ($not_test_guarded_fields || !in_array($field, $this->guarded_fields, true))
						$attributes[$field] = $this->$field;
				$response = $this->_insert($attributes);

			} else {
				$dirty_fields = array();
				$not_test_guarded_fields = sizeof($this->guarded_fields) === 0;
				foreach($this->untouched as $field => $value)
					if ($this->$field !== $value && 
						($not_test_guarded_fields || !in_array($field, $this->guarded_fields, true))
					)
						$dirty_fields[$field] = $this->$field;

				if (sizeof($dirty_fields) > 0)
					$response = $this->_update($dirty_fields);
			}
			return $response;
		}

		protected function _update($data, $options = array())
		{
			if (!is_array($data) || self::has_array_in_multiarray_element($data))
				throw new ErrorException('$data parameter must be an mapping array [column => value] or an array of mapping arrays [i => [column => value]] ');

			if (!is_array($options))
				throw new ErrorException('invalid $options array [valid arguments=table,where]');

			if (isset($options['table'])) {
				if(!is_string($options['table']))
					throw new ErrorException('$option "table" argument must be a string');
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
					$query .= $field.' = :__UPDATE__'.$field.',';
				}
				$query = rtrim($query, ',');

				if (!isset($options['where']))
					if ($this->is_primary_key_composite)
						$options['where'] = $this->_id;
					else
						$options['where'] = array($this->primary_key => $this->_id);

				$where = $this->get_where_query_piece($options['where']);
				$params = array_merge($params, $where['params']);
				$query .= $where['query'];

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
				throw new ErrorException('invalid $options array [valid arguments=table, fields, tables_join, where, group_by, order_by, limit]');

			if (isset($options['table'])) {
				if(!is_string($options['table']))
					throw new ErrorException('$option "table" argument must be a string');
				$table = $options['table'];
			} else 
				$table = $this->table;
		
			$query = 'SELECT';

			if (!isset($options['fields']))
				$query .= ' * ';
			else if (!is_array($options['fields'])) 
				throw new ErrorException('$option argument "fields" must be an array or a mapping array [field => as_name]');
			else $query .= $this->get_fields_query_piece($options['fields']);

			$query .= ' FROM ' . $table;

			if (isset($options['tables_join'])) {
				if (!is_array($options['tables_join']))
					throw new ErrorException('
						$options argument "tables_join" must be an array [table_join_1, table_join_2..], 
						a mapping array [[table_join => table_join_id]..], 
						an array mapping a array map [[table_join => [$table_join_jump => $tables_join_id]]..],
						an array mapping and array map to a two sized array
						[[table_join => [table_join_jump => [table_join_id, table_join_jump_id]]]..]
						or an array mapping an array map that maps fields
						[[table_join => [table_join_jump => [table_join_id_field1 => table_join_jump_field2]]..]

					');
					$query .= $this->get_join_query_piece($options['tables_join'], $table);
			}

			if (isset($options['where'])) {
				$params = array();
				if (!is_array($options['where']))
					throw new ErrorException('
						$options argument "where" must be a mapping array [field => value]
						or an array of mapping arrays (of conditional of comparison) and after the first argument, de second array must 
						have a conditional mapping
						[[field1 => value1], [field2 => [conditional ("AND" or "OR") => value2]..],
						[[field1 => [comparison ("=", "<>", "LIKE") => value1]],
						[field2 => [conditional ("AND" or "OR") => [comparison => value2]..]
						(obs.: include ! after the field name for a not equal comparison)
					');
				$where = $this->get_where_query_piece($options['where']);
				$params = $where['params'];
				$query .= $where['query'];			
			}

			foreach (array('group_by' => ' GROUP BY ' ,'order_by' => ' ORDER BY ') as $argument_index => $argument)
				if (isset($options[$argument_index])) {
					if (!is_string($options[$argument_index]) && !is_array($options[$argument_index]))
						throw new ErrorException('$options "'.$argument_index.'" argument must be a string field or an array of string fields (order matters)');

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
					throw new ErrorException('$options "limit" argument must be a numeric value (string or number)');		
				$query .= ' LIMIT '.$options['limit'];
			}

			try {
				$statement = $this->connection->prepare($query);

				if (isset($options['where'])) {
					$statement->execute($params);
				}	
				else
					$statement->execute();
				return $statement->fetchAll(PDO::FETCH_ASSOC);
			}	catch (PDOException $e) {
				throw new ErrorException('Failed to perform select query (table:'.$table.') > ' . $e->getMessage());
			}

		}

		protected function _delete($options = array())
		{
			if (!is_array($options))
				throw new ErrorException('invalid $options array [valid arguments=table, fields, tables_join, where, group_by, order_by, limit]');

			if (isset($options['table'])) {
				if(!is_string($options['table']))
					throw new ErrorException('$option "table" argument must be a string');
				$table = $options['table'];
			} else 
				$table = $this->table;

			$query = 'DELETE FROM '.$table;

			if (isset($options['where'])) {
				$where = $this->get_where_query_piece($options['where']);
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

		private function get_fields_query_piece($fields)
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

		private function get_join_query_piece($tables_join, $table)
		{
			$query = '';
			foreach ($tables_join as $index => $table_join)
				if (!is_array($table_join)) 
					$query .= ' JOIN ' . $table_join . ' USING (' . $table . '_id)';
				else {
					$table_join_element = key($table_join);
					$table_join_column = reset($table_join);

					if (is_array($table_join_column)) {
						if (self::is_associative($table_join_column)) {
							$first = true;
							$query .= ' JOIN '.$table_join_element.' ON (';
							foreach ($table_join_column as $table_join_column_condition => $table_join_column_jump_and_field) {
								if ($first) {
									$first = false;
									$query .= $table_join_element.'.'.$table_join_column_condition.' = '.$table.'.'.$table_join_column_jump_and_field;
								} else
									$query .= $table_join_column_condition.' '.$table_join_element.'.'.key($table_join_column_jump_and_field).' = '.$table.'.'.reset($table_join_column_jump_and_field);
							}
							$query .= ')';
						} else 
							$query .= ' JOIN '.$table_join_element.' ON ('.$table_join_element.'.'.$table_join_column[1].' = '.$table.'.'.$table_join_column[0];
					} else
						$query .= ' JOIN '.$table_join_element.' ON ('.$table_join_element.'.'.$table_join_column.' = '.$table.'.'.$table_join_column;
				}
			return $query;
		}	

		private function get_where_query_piece($where)
		{

			$query = ' WHERE ';
			$params = array();
			if (sizeof($where) === 1)  {
				$value = reset($where);
				if (is_array($value)) {
					$field = key($where);
					$query .= $field.' '.key($value).' :__WHERE__'.$field;
					$params[':__WHERE__'.$field] = reset($value);
				} else {
					$field = key($where);
					$query .= $field.'= :__WHERE__'.$field;
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
							$query .= $field.' '.key($value).' :__WHERE__'.$index.'_'.$field;
							$params[':__WHERE__'.$index.'_'.$field] = reset($value);
						} else {
							$query .= $field.'= :__WHERE__'.$index.'_'.$field;
							$params[':__WHERE__'.$index.'_'.$field] = $value;
						}
					} else {
						$condition = key($clause);
						$field_and_value = reset($clause);
						$field = key($field_and_value);
						$value = reset($field_and_value); 
						if (is_array($value)) {
							$query .= ' '.$condition.' '.$field.' '.key($value).':__WHERE__'.$index.'_'.$field;
							$params[':__WHERE__'.$index.'_'.$field] = reset($value);
						} else {
							$query .= ' '.$condition.' '.$field.'= :__WHERE__'.$index.'_'.$field;
							$params[':__WHERE__'.$index.'_'.$field] = $value;
						}
					}
				}
			}
			return array('params' => $params, 'query' => $query);
		}

		private function load_fields($find_action, $value) {

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
						throw new ErrorException("Can't find row by id");
				} else if ($find_action === 'where') {
					$row = $value;
				}
			}

			foreach($this->describe_table() as $description) {
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
					$value = $this->parse_type(preg_replace('/\([^)]*\)/', '', $description['Type']), !is_null($find_action) ? $row[$description['Field']] : $description['Default']);
					$this->untouched[$description['Field']] = $value;
					$this->{$description['Field']} = $value;
				}
				
			}
		}

		private function describe_table()
		{	
			if (is_null($this->table_description)) {
				$statement = $this->connection->prepare('DESCRIBE '.$this->table);
				$statement->execute();
				$this->table_description = $statement->fetchAll(PDO::FETCH_ASSOC);
			} 
			return $this->table_description;
		}

		public function get_table_description() 
		{
			return $this->describe_table();
		}

		private static function parse_type($type, $value) 
		{
			switch($type) {
				case "date":
					$parsed_value = DateTime::createFromFormat(DB_DATE_FORMAT, $value);
					break;
				case "datetime":
					$parsed_value = DateTime::createFromFormat(DB_DATETIME_FORMAT, $value);
					break;
				case "timestamp":
					$parsed_value = DateTime::createFromFormat('U', $value);
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

		protected function table()
		{
			return $this->table;
		}

		public static function get_tablename()
		{
			$entity = new static(array('connection' => false));
			return $entity->table();
		}

		protected function has_one($model, $primary_key = null)
		{
			if (!is_string($model))
				throw new ErrorException('$model argument must be a string');

			if (!is_string($primary_key) || !empty($primary_key))
				$primary_key = $this->primary_key;

			$model_table = call_user_func($model.'::get_tablename');
			$this->$model_table = call_user_func_array($model.'::where', array(array($primary_key => $this->_id), $this->connection))->first();
			return $this;		
		} 

		protected function belongs_to($model, $primary_key = null)
		{
			if (!is_string($model))
				throw new ErrorException('$model argument must be a string');

			if (!is_string($primary_key) || !empty($primary_key))
				$primary_key = $this->primary_key;
			
			$model_table = call_user_func($model.'::get_tablename');
			$this->$model_table = call_user_func_array($model.'::_concatenate_relation', array(array($primary_key => $this->_id), array($this->table), $this->connection))->first();
			return $this;
		}

		protected function has_many($model, $primary_key = null)
		{
			if (!is_string($model))
				throw new ErrorException('$model argument must be a string');

			if (!is_string($primary_key) || !empty($primary_key))
				$primary_key = $this->primary_key;

			$model_table = call_user_func($model.'::get_tablename');

			if (property_exists(new $model, $primary_key))
				$this->$model_table = call_user_func_array($model.'::where', array(array($primary_key => $this->_id), $this->connection));
			else
				$this->$model_table = call_user_func_array($model.'::_concatenate_relation', array(array($primary_key => $this->_id), array($this->table.'_to_'.$model_table), $this->connection));
			
			return $this;		
		}

		protected function belongs_to_many($model, $primary_key = null)
		{
			if (!is_string($model))
				throw new ErrorException('$model argument must be a string');

			if (!is_string($primary_key) || !empty($primary_key))
				$primary_key = $this->primary_key;
			
			$model_table = call_user_func($model.'::get_tablename');;
			if (property_exists($this, $primary_key))
				$this->$model_table = call_user_func_array($model.'::_concatenate_relation', array(array($primary_key => $this->_id), array($this->table), $this->connection));
			else
				$this->$model_table = call_user_func_array($model.'::_concatenate_relation', array(array($primary_key => $this->_id), array($model_table.'_to_'.$this->table), $this->connection));

			return $this;
		}

		private static function has_array_in_multiarray_element($array)
		{
			return (is_array(reset($array)) && is_array(reset(reset($array))));
		}

		private static function is_associative($array)
		{
			return array_keys($array) !== range(0, count($array) - 1);
		}

		private static function get_tablename_from_class($class)
		{
			return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $class));
		}

	}