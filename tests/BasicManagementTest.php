<?php
	class BasicManagementTest extends PHPUnit_Framework_TestCase {

		protected $connection;
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

		private function selectFromTestTable($fields = '*', $specify = '') 
		{
			return $this->connection->query("SELECT $fields FROM `".$this->database_prefix."test_table` $specify")->fetchAll(PDO::FETCH_ASSOC);
		}

		/* tests */ 

		public function testLoadFields() 
		{
			$test_table = new TestTable(array('connection' => $this->connection));

			$this->assertTrue(property_exists($test_table, 'id'), 'on exists id');
			$this->assertNull($test_table->id, 'on value id');
			$this->assertTrue(property_exists($test_table, 'name'), 'on exists name');
			$this->assertEquals("", $test_table->name, 'on value name');
			$this->assertTrue(property_exists($test_table, 'age'), 'on exists age');
			$this->assertEquals(0, $test_table->age, 'on value age');
			$this->assertTrue(property_exists($test_table, 'someother_field'), 'on exists someother_field');
			$this->assertEquals('default value', $test_table->someother_field, 'on value someother_field');
			$this->assertTrue(property_exists($test_table, 'boolean_field'), 'on exists boolean_field');
			$this->assertEquals(false, $test_table->boolean_field, 'on value boolean_field');

		}

		public function testLoadAnObjectFromWhere() 
		{
			$test_table = TestTable::where(array('name' => 'John Doe'), $this->connection);
			$test_table = $test_table->first();

			$this->assertEquals(20, $test_table->age); 
			$this->assertEquals('Some other value.', $test_table->someother_field);
			$this->assertEquals(true, $test_table->boolean_field);

		}

		public function testLoadAnObjectFromId() {
			$test_table = TestTable::find_by_id(5, $this->connection);

			$this->assertEquals('Doe Arruan Jhon', $test_table->name);
			$this->assertEquals(11, $test_table->age);
			$this->assertEquals(false, $test_table->boolean_field);

		}

		public function testLoadObjects() 
		{
			$test_table = TestTable::where(array('age' => 21), $this->connection);

			$names = array(
				array('name' => 'Doe Jhon'),
				array('name' => 'Doe Jhon New Guy'),
			);

			foreach($test_table as $index => $object)
			{
 				$this->assertEquals($names[$index]['name'], $object->name);
			}

		}

		public function testSaveObject()
		{
			$test_table = new TestTable(array('connection' => $this->connection));

			$test_table->name = 'Test Name';
			$test_table->age = 15;
			$test_table->someother_field = 'Some value to save';
			$test_table->boolean_field = true;

			$test_table->save();

			$test_table_row = $this->selectFromTestTable('name, age, someother_field, boolean_field', 'WHERE name = "Test Name"');

			$this->assertEquals(1, sizeof($test_table_row));
			$this->assertEquals('Test Name', $test_table_row[0]['name']);
			$this->assertEquals(15, $test_table_row[0]['age']);
			$this->assertEquals('Some value to save', $test_table_row[0]['someother_field']);
			$this->assertEquals(1, $test_table_row[0]['boolean_field']);
		}

		public function testUpdateObjectSaving()
		{
			$test_table = TestTable::find_by_id(10, $this->connection);

			$test_table->name = 'Jhon NewName';
			$test_table->age = 22;
			$test_table->boolean_field = false;

			$test_table->save();

			$test_table_row = $this->selectFromTestTable('name, age, someother_field, boolean_field', 'WHERE test_table_id = 10');

			$this->assertEquals('Jhon NewName', $test_table_row[0]['name']);
			$this->assertEquals(22, $test_table_row[0]['age']);
			$this->assertEquals('Another some other value.', $test_table_row[0]['someother_field']);
			$this->assertEquals(0, $test_table_row[0]['boolean_field']);

		}

	}