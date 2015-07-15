<?php
	//require_once('config.php');

	class EntityManager {
		protected $connection;
		protected $class_name;
		protected $table;
		protected $table_description;

		private $_id;
		private $untouched;
		//private $untouched_raw;

		public function __construct($options = array()) {
			
			$this->_id = null;
			$this->class_name = get_class($this);
			$this->table = self::get_tablename_from_class($this->class_name);
			$this->guarded = array();
			$this->untouched = array();
			//$this->untouched_raw = array();

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

				$this->load_fields($options['find_action'], $options['find_value']);
			} catch (PDOException $e) {
				throw new ErrorException('Failed to connect to the database or load fields: ' . $e->getMessage());
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

		public static function where_with(array $conditions, $with, $connection = null)
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

		public function update($values)
		{
			$fields = array_keys($this->untouched);
			foreach($values as $field => $value)
				if (in_array($field, $fields, true))
					$this->$field = $value;
			$this->save();
		}

		public function assert($model, $ids)
		{
			$table_relationed = self::get_tablename_from_class($model);
			$pivot_table = $this->table.'_to_'.$table_relationed;

			$this->_delete(array(
				'table' => $pivot_table,
				'where' => array($this->table.'_id' => $this->_id ),
			));

			$data = array();
			foreach ($ids as $id)
				array_push($data, array($this->table.'_id' => $this->_id, $table_relationed.'_id' => $id));

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

				$this->_id = $this->connection->lastInsertId();
				
			} catch (PDOException $e) {
				throw new ErrorException('Failed to perform insertion query (table:'.$table.') > ' . $e->getMessage());
			}

			$this->id = $this->_id;
		}

		public function save() 
		{
			$response = false;
			if (is_null($this->_id)) {
				$attributes = array();
				$guarded_fields = $this->guarded_fields();
				$not_test_guarded_fields = sizeof($guarded_fields) === 0;
				foreach (array_keys($this->untouched) as $field)
					if ($not_test_guarded_fields || !in_array($field, $guarded_fields, true))
						$attributes[$field] = $this->$field;
				$response = $this->_insert($attributes);

			} else {
				$dirty_fields = array();
				$guarded_fields = $this->guarded_fields();
				$not_test_guarded_fields = sizeof($guarded_fields) === 0;
				foreach($this->untouched as $field => $value)
					if ($this->$field !== $value && 
						($not_test_guarded_fields || !in_array($field, $guarded_fields, true))
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
					$options['where'] = array($table.'_id' => $this->_id);

				$where = $this->get_where_query_piece($options['where']);
				$params = array_merge($params, $where['params']);
				$query .= $where['query'];

				try {
					$statement = $this->connection->prepare($query);
					$statement->execute($params);
					return $statement->rowCount();
				} catch (PDOException $e) {
					throw new ErrorException('Failed to perform update query (table:'.$table.')');
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
				if (isset($options['where']))
					$statement->execute($params);
				else
					$statement->execute();
				return $statement->fetchAll(PDO::FETCH_ASSOC);
			}	catch (PDOException $e) {
				throw new ErrorException('Failed to perform select query (table:'.$table.') >' . $e->getMessage());
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
				throw new ErrorException('Failed to perform deletion query >' . $e->getMessage());
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
				$index = 0;
				$first = true;
				foreach ($where as $condition => $field_and_value) {						
					if ($first) {
						if (is_array($field_and_value)) {
							$first = false;
							$query .= $condition.' '.key($field_and_value).' :__WHERE__'.$index.'_'.$condition;
							$params[':__WHERE__'.$index++.'_'.$condition] = reset($field_and_value);
						} else {
							$first = false;
							$query .= $condition.'= :__WHERE__'.$index.'_'.$condition;
							$params[':__WHERE__'.$index++.'_'.$condition] = $field_and_value;
						}
					} else {
						$field = key($field_and_value);
						$value = reset($field_and_value); 
						if (is_array($value)) {
							$query .= ' '.$condition.' '.$field.' '.key($value).':'.$index.'_'.$field;
							$params[':'.$index++.'_'.$field] = reset($value);
						} else {
							$query .= ' '.$condition.' '.$field.'= :'.$index.'_'.$field;
							$params[':'.$index++.'_'.$field] = $value;
						}
					}
				}
			}
			return array('params' => $params, 'query' => $query);
		}

		private function load_fields($find_action, $value) {

			if (!is_null($find_action)) {
				if ($find_action === 'find_by_id') {
					$row = $this->_select(array('where' => array($this->table.'_id' => $value), 'limit' => 1));
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
					$this->id = $id; 
					$this->_id = $id;
				} else {
					$value = $this->parse_type(preg_replace('/\([^)]*\)/', '', $description['Type']), !is_null($find_action) ? $row[$description['Field']] : $description['Default']);
					//$this->untouched_raw[$description['Field']] = !is_null($find_action) ? $row[$description['Field']] : $description['Default'];
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

		protected function guarded_fields()
		{
			return array();
		}

		protected function table()
		{
			return $this->table;
		}

		protected function has_one($model, $field_id = null)
		{
			if (!is_string($model))
				throw new ErrorException('$model argument must be a string');

			if (!is_string($field_id))
				$field_id = $this->table.'_id';

			$model_table = self::get_tablename_from_class($model);
			$this->$model_table = call_user_func_array($model.'::where', array(array($field_id => $this->_id), $this->connection))->first();
			return $this;		
		} 

		protected function belongs_to($model, $field_id = null)
		{
			if (!is_string($model))
				throw new ErrorException('$model argument must be a string');

			if (!is_string($field_id))
				$field_id = $this->table.'_id';
			
			$model_table = self::get_tablename_from_class($model);
			$this->$model_table = call_user_func_array($model.'::where_with', array(array($field_id => $this->_id), array($this->table), $this->connection))->first();
			return $this;
		}

		protected function has_many($model, $field_id = null)
		{
			if (!is_string($model))
				throw new ErrorException('$model argument must be a string');

			if (!is_string($field_id))
				$field_id = $this->table.'_id';

			$model_table = self::get_tablename_from_class($model);

			if (property_exists(new $model, $field_id))
				$this->$model_table = call_user_func_array($model.'::where', array(array($field_id => $this->_id), $this->connection));
			else
				$this->$model_table = call_user_func_array($model.'::where_with', array(array($field_id => $this->_id), array($this->table.'_to_'.$model_table), $this->connection));
			
			return $this;		
		}

		protected function belongs_to_many($model, $field_id = null)
		{
			if (!is_string($model))
				throw new ErrorException('$model argument must be a string');

			if (!is_string($field_id))
				$field_id = $this->table.'_id';
			
			$model_table = self::get_tablename_from_class($model);

			if (property_exists($this, $field_id))
				$this->$model_table = call_user_func_array($model.'::where_with', array(array($field_id => $this->_id), array($this->table), $this->connection));
			else
				$this->$model_table = call_user_func_array($model.'::where_with', array(array($field_id => $this->_id), array($model_table.'_to_'.$this->table), $this->connection));

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