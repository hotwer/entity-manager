<?php
	class CustomizationOfNamesTest extends PHPUnit_Framework_TestCase
	{
		protected $connection = null;
		protected $database_prefix = '';

		public function __construct() 
		{
			try {
				$this->connection = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' .  DB_DATABASE, 
					DB_USERNAME, DB_PASSWORD
				);
				$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			} catch (PDOException $e) {
				throw new ErrorException('Failed to connect to the database/start it > ' . $e->getMessage());
			}
		}

		public function __destruct()
		{
			$this->connection = null;
		}

		public function selectFromCustomizedTable($specify)
		{
			return $this->connection->query("SELECT `custom_id`, `field` FROM my_custom_table $specify")->fetchAll(PDO::FETCH_ASSOC);
		}

		public function selectFromTableWithCompositeKey($specify)
		{
			return $this->connection->query("SELECT `first_key`, `second_key`, `field` FROM table_with_composite_key $specify")->fetchAll(PDO::FETCH_ASSOC);
		}

		public function testGetOnCustomizedTableName()
		{
			$customized_table = CustomizedTable::find_by_id(1, $this->connection);
			$this->assertEquals('uber_default_value', $customized_table->field);

			$customized_table = CustomizedTable::where(array('field' => array('LIKE' => 'some value%')), $this->connection)->first();
			$this->assertEquals(2, $customized_table->id);
		}

		public function testCreateOnCustomizedTableName()
		{
			$customized_table = CustomizedTable::create(array(
				'field' => 'my created value',
			), $this->connection);

			$specify_query = "WHERE custom_id = $customized_table->id";			

			$customized_table = new CustomizedTable(array('connection' => $this->connection));
			$customized_table->field = 'my another created value';
			$customized_table->save();

			$specify_query .= " OR custom_id = $customized_table->id ORDER BY custom_id";
			
			$customized_table_row = $this->selectFromCustomizedTable($specify_query);
			$this->assertEquals('my created value', $customized_table_row[0]['field']);
			$this->assertEquals('my another created value', $customized_table_row[1]['field']);
		}

		public function testUpdateOnCustomizedTableName()
		{
			CustomizedTable::find_by_id(1, $this->connection)->update(array('field' => 'some new value'));	
			$customized_table_row = $this->selectFromCustomizedTable('WHERE custom_id = 1');
			$this->assertEquals('some new value', $customized_table_row[0]['field']); 
		}

		public function testGetOnCustomizedPrimaryKey()
		{
			$customized_table = TableWithCompositeKey::find_by_id(array('first_key' => 1, 'second_key' => 2), $this->connection);
			$this->assertEquals('some other value', $customized_table->field);

			$customized_table = TableWithCompositeKey::where(array('field' => array('LIKE' => '%default_uber')), $this->connection)->first(); 
			$this->assertEquals(array('first_key' => 1, 'second_key' => 1), $customized_table->id);
		}

		public function testCreateOnCustomizedPrimaryKey()
		{
			// $customized_table = TableWithCompositeKey::create(array(
			// 	'field' => 'my created value',
			// ), $this->connection);

			// $specify_query = "WHERE custom_id = $customized_table->id";			

			// $customized_table = new TableWithCompositeKey(array('connection' => $this->connection));
			// $customized_table->field = 'my another created value';
			// $customized_table->save();

			// $specify_query .= " OR custom_id = $customized_table->id ORDER BY custom_id";
			
			// $customized_table_row = $this->selectFromTableWithCompositeKey($specify_query);
			// $this->assertEquals('my created value', $customized_table_row[0]['field']);
			// $this->assertEquals('my another created value', $customized_table_row[1]['field']);
			$this->markTestIncomplete();
		}

		public function testUpdateOnCustomizedPrimaryKey()
		{
			// TableWithCompositeKey::find_by_id(1, $this->connection)->update(array('field' => 'some new value'));	
			// $customized_table_row = $this->selectFromTableWithCompositeKey('WHERE custom_id = 1');
			// $this->assertEquals('some new value', $customized_table_row[0]['field']); 
			$this->markTestIncomplete();
		}

		public function testGetOnTableWithCompositeKey()
		{
			$this->markTestIncomplete();
		}

		public function testCreateOnTableWithCompositeKey()
		{
			$this->markTestIncomplete();
		}

		public function testUpdateOnTableWithCompositeKey()
		{
			$this->markTestIncomplete();
		}
	}