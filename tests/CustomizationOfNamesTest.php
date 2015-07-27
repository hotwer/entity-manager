<?php
class CustomizationOfNamesTest extends SetupTests
{
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
        $customized_table = CustomizedTable::findById(1, $this->connection);
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
        CustomizedTable::findById(1, $this->connection)->update(array('field' => 'some new value'));    
        $customized_table_row = $this->selectFromCustomizedTable('WHERE custom_id = 1');
        $this->assertEquals('some new value', $customized_table_row[0]['field']); 
    }

    public function testGetOnTableWithCompositeKey()
    {
        $customized_table = TableWithCompositeKey::findById(array('first_key' => 1, 'second_key' => 2), $this->connection);
        $this->assertEquals('some other value', $customized_table->field);

        $customized_table = TableWithCompositeKey::where(array('field' => array('LIKE' => '%default_uber')), $this->connection)->first(); 
        $this->assertEquals(array('first_key' => 1, 'second_key' => 1), $customized_table->id);
    }

    public function testCreateOnTableWithCompositeKey()
    {
        $customized_table = TableWithCompositeKey::create(array(
            'first_key' => 3,
            'second_key' => 4,
            'field' => 'my created value on composite table',
        ), $this->connection);

        $specify_query = "WHERE (first_key = ".$customized_table->id['first_key']." AND second_key = ".$customized_table->id['second_key'].")";         

        $customized_table = new TableWithCompositeKey(array('connection' => $this->connection));
        $customized_table->id = array('first_key' => 4, 'second_key' => 3);
        $customized_table->field = 'my another created value on composite table';
        $customized_table->save();

        $specify_query .= " OR (first_key = ".$customized_table->id['first_key']." AND second_key =". $customized_table->id['second_key'].") ORDER BY first_key";
        
        $customized_table_row = $this->selectFromTableWithCompositeKey($specify_query);
        $this->assertEquals('my created value on composite table', $customized_table_row[0]['field']);
        $this->assertEquals('my another created value on composite table', $customized_table_row[1]['field']);
    }

    public function testUpdateOnTableWithCompositeKey()
    {
        TableWithCompositeKey::findById(array('first_key' => 1, 'second_key' => 1), $this->connection)
            ->update(array('field' => 'some new value'));   
        $customized_table_row = $this->selectFromTableWithCompositeKey('WHERE (first_key = 1 AND second_key = 1) ');
        $this->assertEquals('some new value', $customized_table_row[0]['field']); 
        $this->markTestIncomplete();
    }

    public function testGetOnCustomizedPrimaryKey()
    {
        $this->markTestIncomplete();
        # supposed to be already tested in first test
        # but must be an atomic test
    }
    
    public function testCreateOnCustomizedPrimaryKey()
    {
        $this->markTestIncomplete();
        # supposed to be already tested in second test
        # but must be an atomic test
    }

    public function testUpdateOnCustomizedPrimaryKey()
    {
        $this->markTestIncomplete();
        # supposed tro be already teste in third test
        # but must be an atomic test
    }
}
