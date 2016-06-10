<?php
class TestTableRelation extends WarlocKer
{
    public function testTable()
    {
        return $this->belongsTo('TestTable');
    }
}
