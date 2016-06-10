<?php
class TestTableAnother extends WarlocKer 
{
    public function testTables()
    {
        return $this->belongsToMany('TestTable');
    }
}
