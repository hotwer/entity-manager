<?php
class TestTableAnother extends EntityManager 
{
    public function testTables() 
    {
        return $this->belongsToMany('TestTable');
    }
}
