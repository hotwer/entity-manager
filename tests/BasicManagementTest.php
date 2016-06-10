<?php

class BasicManagementTest extends SetupTests
{
    public function testLoadFields()
    {
        $test_table = new TestTable(array('connection' => $this->connection));
        $this->assertTrue(isset($test_table->id), 'on exists id');
        $this->assertNull($test_table->id, 'on value id');
        $this->assertTrue(isset($test_table->name), 'on exists name');
        $this->assertEquals("", $test_table->name, 'on value name');
        $this->assertTrue(isset($test_table->age), 'on exists age');
        $this->assertEquals(0, $test_table->age, 'on value age');
        $this->assertTrue(isset($test_table->someother_field), 'on exists someother_field');
        $this->assertEquals('default value', $test_table->someother_field, 'on value someother_field');
        $this->assertTrue(isset($test_table->boolean_field), 'on exists boolean_field');
        $this->assertEquals(false, $test_table->boolean_field, 'on value boolean_field');
    }

    public function testLoadAnObjectFromWhere()
    {
        $test_table = TestTable::where(array('name' => 'John Doe'), $this->connection)->first();

        $this->assertEquals(20, $test_table->age);
        $this->assertEquals('Some other value.', $test_table->someother_field);
        $this->assertEquals(true, $test_table->boolean_field);

    }

    /* tests */

    public function testLoadAnObjectFromId()
    {
        $test_table = TestTable::findById(5, $this->connection);

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

        foreach ($test_table as $index => $object) {
            $this->assertEquals($names[$index]['name'], $object->name);
        }

    }

    public function testLoadObjectsFromWhereRaw()
    {
        $test_table = TestTable::whereRaw("age = ?", array(21), $this->connection);

        $names = array(
            array('name' => 'Doe Jhon'),
            array('name' => 'Doe Jhon New Guy'),
        );

        foreach ($test_table as $index => $object) {
            $this->assertEquals($names[$index]['name'], $object->name);
        }
    }

    public function testDontFindAnyObjectsInFindById()
    {
        $test_table = TestTable::findById(0, $this->connection);

        $this->assertNull($test_table);
    }

    /**
     * @expectedException        ErrorException
     * @expectedExceptionMessage Cannot find entity by id
     */
    public function testDontFindAnyObjectsInFindByIdOrFail()
    {
        TestTable::findByIdOrFail(0, $this->connection);
    }

    public function testDontFindAnyObjectsInWhere()
    {
        $test_table_collection = TestTable::where(array('age' => 0), $this->connection);

        $this->assertEquals(0, $test_table_collection->size());
        $this->assertNull($test_table_collection->first());
    }

    public function testDontFindAnyObjectsInWhereRaw()
    {
        $test_table_collection = TestTable::whereRaw("age = :_age", array(':_age' => 0), $this->connection);

        $this->assertEquals(0, $test_table_collection->size());
        $this->assertNull($test_table_collection->first());
    }

    public function testSaveObject()
    {
        $test_table = new TestTable(array('connection' => $this->connection));

        $test_table->name = 'Test Name';
        $test_table->age = 15;
        $test_table->someother_field = 'Some value to save';
        $test_table->boolean_field = true;

        $test_table->save();

        $test_table_row = $this->selectFromTestTable('name, age, someother_field, boolean_field',
            'WHERE name = "Test Name"');

        $this->assertEquals(1, sizeof($test_table_row));
        $this->assertEquals('Test Name', $test_table_row[0]['name']);
        $this->assertEquals(15, $test_table_row[0]['age']);
        $this->assertEquals('Some value to save', $test_table_row[0]['someother_field']);
        $this->assertEquals(1, $test_table_row[0]['boolean_field']);
    }

    public function selectFromTestTable($fields = '*', $specify = '')
    {
        return $this->connection->query("SELECT $fields FROM `test_table` $specify")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function testUpdateObjectSaving()
    {
        $test_table = TestTable::findById(10, $this->connection);

        $test_table->name = 'Jhon NewName';
        $test_table->age = 22;
        $test_table->boolean_field = false;

        $test_table->save();

        $test_table_row = $this->selectFromTestTable('name, age, someother_field, boolean_field',
            'WHERE test_table_id = 10');

        $this->assertEquals('Jhon NewName', $test_table_row[0]['name']);
        $this->assertEquals(22, $test_table_row[0]['age']);
        $this->assertEquals('Another some other value.', $test_table_row[0]['someother_field']);
        $this->assertEquals(0, $test_table_row[0]['boolean_field']);

    }

    public function testDeleteSavedObject()
    {
        $this->markTestIncomplete();
    }

    public function testDeleteUnsavedObject()
    {
        $this->markTestIncomplete();
    }

    public function testDeleteObjectAfterSaving()
    {
        $test_table = TestTable::create(array(
            'name' => 'Abc Jhon',
            'age' => 11,
            'someother_field' => 'aaaaa',
        ), $this->connection);

        $test_table_row = $this->selectFromTestTable('*', 'WHERE name = "Abc Jhon"');

        $this->assertEquals(11, $test_table_row[0]['age']);

        $test_table->delete();

        $test_table_row = $this->selectFromTestTable('*', 'WHERE name = "Abc Jhon"');

        $this->assertEquals(0, sizeof($test_table_row));
    }

    public function testNewObjectWithTimeFields()
    {
        $time = new Time(array('connection' => $this->connection));

        $this->assertEquals(date('d/m/Y'), $time->my_date->format(WarlocKer::DATE_FORMAT));
        $this->assertEquals('01/01/1900', $time->my_date_default->format(WarlocKer::DATE_FORMAT));
        $this->assertEquals(date('d/m/Y H'), $time->my_datetime->format("d/m/Y H"));
        $this->assertEquals('01/01/1900 00', $time->my_datetime_default->format("d/m/Y H"));
        //$this->assertEquals(date('d/m/Y H'), $time->my_timestamp->format("d/m/Y H"));
        $this->assertEquals(date('d/m/Y H'), $time->my_timestamp_default->format("d/m/Y H"));
    }

    public function testLoadObjectWithTimeFields()
    {
        $time = Time::findById(1, $this->connection);

        $this->assertEquals('09/09/2009 09:09:59', $time->my_timestamp->format(WarlocKer::DATETIME_FORMAT));
        $this->assertEquals('10/10/2010 03:03:03', $time->my_datetime->format(WarlocKer::DATETIME_FORMAT));
        $this->assertEquals('11/11/2011', $time->my_date->format(WarlocKer::DATE_FORMAT));
    }

    public function testSaveObjectWithTimeFields()
    {
        $time = new Time(array('connection' => $this->connection));

        $time->my_date = new DateTime('tomorrow');
        $date = $time->my_date->format(WarlocKer::DB_DATE_FORMAT);
        $datetime = $time->my_datetime->modify("-1 day")->format(WarlocKer::DB_DATETIME_FORMAT);
        $time->save();

        $time_row = $this->selectFromTime('WHERE time_id = 3');

        $this->assertEquals($date, $time_row[0]['my_date']);
        $this->assertEquals($datetime, $time_row[0]['my_datetime']);
    }

    public function selectFromTime($specify)
    {
        return $this->connection->query("SELECT * FROM time $specify")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function testUpdateObjectWithTimeField()
    {
        $time = Time::findById(2, $this->connection);

        $time->my_date->modify("+2 days");
        $time->save();

        $time_row = $this->selectFromTime('WHERE time_id = 2');

        $this->assertEquals('2011-11-13', $time_row[0]['my_date']);
        $this->assertEquals('2009-09-09 09:10:00', $time_row[0]['my_timestamp']);
    }
}
