<?php
class TestTableRelation extends EntityManager
{
    public function testTable() 
    {
        return $this->belongsTo('TestTable');
    }
}
