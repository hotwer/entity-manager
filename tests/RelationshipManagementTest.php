<?php
	class RelationshipManagementTest extends PHPUnit_Framework_TestCase
	{
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

		public function testGetObjectRelationshipOneToMany() 
		{
			$test_table = TestTable::findById(11, $this->connection)->testTableRelations();

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

			$test_table = TestTable::findById(12, $this->connection)->testTableRelations();

			$this->assertEquals('My field is not empty', $test_table->test_table_relation->index(0)->relation_field);
			$this->assertEquals('And now has usefull information', $test_table->test_table_relation->index(1)->relation_field);
	
		}

		public function testUpdateObjectsRelationshipOneToMany() 
		{
			$test_table = TestTable::findById(13, $this->connection)->testTableRelations();
			$test_table->test_table_relation->first()->update(array('non_existing_field' => 'to useless value',  'relation_field' => 'Some new test value'));

			$test_table_relation_row = $this->selectFromTestTableRelation('relation_field', 'WHERE test_table_relation_id = 6;');
			
			$this->assertEquals('Some new test value', $test_table_relation_row[0]['relation_field']);
		}

		public function testGetObjectsRelationshipManyToMany() 
		{
			$test_table = TestTable::findById(14, $this->connection)->testTableAnothers();

			$this->assertEquals(2, $test_table->test_table_another->size());
			$this->assertEquals('Zigs', $test_table->name);
			$this->assertEquals('Ward', $test_table->test_table_another->index(0)->some_field);
			$this->assertEquals('Zhonya',  $test_table->test_table_another->index(1)->some_field);

			$test_table = TestTable::findById(15, $this->connection)->testTableAnothers();

			$this->assertEquals(2, $test_table->test_table_another->size());
			$this->assertEquals('Tresh', $test_table->name);
			$this->assertEquals('Ward', $test_table->test_table_another->index(0)->some_field);
			$this->assertEquals('Zhonya', $test_table->test_table_another->index(1)->some_field);
		}

		public function testInsertNewObjectsRelationshipManyToMany()
		{
			$test_table = TestTable::findById(16, $this->connection);
			$test_table->assert('TestTableAnother', array(3, 4));
			$test_table = TestTable::findById(17, $this->connection);
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

		public function testNotLazyEagerJoinAll()
		{
			$this->markTestIncomplete();
		}


		public function testNotLazyEagerJoinFromId()
		{
			$this->markTestIncomplete();
		}

		public function testNotLazyEagerJoinFromWhere()
		{
			$this->markTestIncomplete();
		}

	}