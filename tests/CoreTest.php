<?php
	class CoreTest extends PHPUnit_Framework_TestCase {

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

		private function selectFromTestTable($fields = '*', $specify = '') 
		{
			return $this->connection->query("SELECT $fields FROM `".$this->database_prefix."test_table` $specify")->fetchAll(PDO::FETCH_ASSOC);
		}

		private function selectFromTestTableRelation($fields = '*', $specify = '')
		{
			return $this->connection->query("SELECT $fields FROM `".$this->database_prefix."test_table_relation` $specify")->fetchAll(PDO::FETCH_ASSOC);
		}

		private function selectFromTestTableAnother($fields = '*', $specify = '') 
		{
			return $this->connection->query("SELECT $fields FROM `".$this->database_prefix."test_table_another` $specify")->fetchAll(PDO::FETCH_ASSOC);
		}

		private function selectFromTestTablePivotTestTableAnother($fields = '*', $specify = '') 
		{
			return $this->connection->query("SELECT $fields FROM `".$this->database_prefix."test_table_to_test_table_another` $specify")->fetchAll(PDO::FETCH_ASSOC);
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

		public function testGetObjectRelationshipOneToMany() 
		{
			$test_table = TestTable::find_by_id(11, $this->connection)->test_table_relations();

			$this->assertEquals("Joana DArc", $test_table->name);
			$this->assertEquals('this field is relationed', $test_table->test_table_relation->index(0)->relation_field);
			$this->assertEquals('But also is not!', $test_table->test_table_relation->index(1)->relation_field);

		}

		public function testInsertNewObjectsRelationshipOneToMany() 
		{
			foreach(array('My field is not empty', 'And now has usefull information') as $value) {
				$test_table_relation = new TestTableRelation(array('connection' => $this->connection));
				$test_table_relation->relation_field = $value;
				$test_table_relation->test_table_id = 12;
				$test_table_relation->save();
			}

			$test_table = TestTable::find_by_id(12, $this->connection)->test_table_relations();

			$this->assertEquals('My field is not empty', $test_table->test_table_relation->index(0)->relation_field);
			$this->assertEquals('And now has usefull information', $test_table->test_table_relation->index(1)->relation_field);
	
		}

		public function testUpdateObjectsRelationshipOneToMany() 
		{
			$test_table = TestTable::find_by_id(13, $this->connection)->test_table_relations();
			$test_table->test_table_relation->first()->update(array('non_existing_field' => 'to useless value',  'relation_field' => 'Some new test value'));

			$test_table_relation_row = $this->selectFromTestTableRelation('relation_field', 'WHERE test_table_relation_id = 6;');
			
			$this->assertEquals('Some new test value', $test_table_relation_row[0]['relation_field']);
		}

		public function testGetObjectsRelationshipManyToMany() 
		{
			$test_table = TestTable::find_by_id(14, $this->connection)->test_table_anothers();

			$this->assertEquals(2, $test_table->test_table_another->size());
			$this->assertEquals('Zigs', $test_table->name);
			$this->assertEquals('Ward', $test_table->test_table_another->index(0)->some_field);
			$this->assertEquals('Zhonya',  $test_table->test_table_another->index(1)->some_field);

			$test_table = TestTable::find_by_id(15, $this->connection)->test_table_anothers();

			$this->assertEquals(2, $test_table->test_table_another->size());
			$this->assertEquals('Tresh', $test_table->name);
			$this->assertEquals('Ward', $test_table->test_table_another->index(0)->some_field);
			$this->assertEquals('Zhonya', $test_table->test_table_another->index(1)->some_field);
		}

		public function testInsertNewObjectsRelationshipManyToMany()
		{
			$test_table = TestTable::find_by_id(16, $this->connection);
			$test_table->assert('TestTableAnother', array(3, 4));
			$test_table = TestTable::find_by_id(17, $this->connection);
			$test_table->assert('TestTableAnother', array(4, 3));

			$test_table_to_another_pivot_row = $this->selectFromTestTablePivotTestTableAnother('test_table_id, test_table_another_id', 'WHERE test_table_id = 16'); 

			$this->assertEquals(3, $test_table_to_another_pivot_row[0]['test_table_another_id']);
			$this->assertEquals(4, $test_table_to_another_pivot_row[1]['test_table_another_id']);

			$test_table_to_another_pivot_row = $this->selectFromTestTablePivotTestTableAnother('test_table_id, test_table_another_id', 'WHERE test_table_id = 17'); 

			$this->assertEquals(4, $test_table_to_another_pivot_row[0]['test_table_another_id']);
			$this->assertEquals(3, $test_table_to_another_pivot_row[1]['test_table_another_id']);

		}

		public function testObjectsHasOneGetting()
		{
			$centers = array(37, 3, 10);
			Circle::all($this->connection)->iterate(function($circle, $index) use ($centers)
			{
				$this->assertEquals($centers[$index], $circle->center()->center->point);
			});
		}

		public function testObjectsBelongsToGetting()
		{
			$circles = array(
				3 => 5.00, 
				2 => 2.50, 
				1 => 3.00
			);

			Center::all($this->connection)->iterate(function($center, $index) use ($circles)
			{ 
				foreach($circles as $id => $value)
					if ($id === $center->id)
						$this->assertEquals($value, $center->circle()->circle->radius);
			});
		}	

		public function testObjectsHasManyGetting()
		{
			$square_values = array(
				1 => array(12.15, 10.00),
				2 => array(32.15, 6.25),
				3 => array(36.80),
			);
			Cube::all($this->connection)->iterate(function($cube, $cube_index) use ($square_values)
			{
				$square_relation = $square_values[$cube->id];
				$cube->squares()->square->iterate(function($square, $square_index) use ($square_relation)
				{	
					$this->assertEquals($square_relation[$square_index], $square->size); 
				});
			});
		}

		public function testObjectsBelongsToManyGetting()
		{
			$sphere_values = array(
				1 => array(0.00, 7.77),
				2 => array(0.00, 7.77),
				3 => array(7.77),
			);
			Circle::all($this->connection)->iterate(function($circle, $circle_index) use ($sphere_values)
			{
				$sphere_relation = $sphere_values[$circle->id];
				$circle->spheres()->sphere->iterate(function($sphere, $sphere_index) use ($sphere_relation)
				{
					$this->assertEquals($sphere_relation[$sphere_index], $sphere->volume);
				});
			});
		}


	}