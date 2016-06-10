<?php
class TestTable extends WarlocKer 
{
    public function testTableRelations()
    {
        return $this->hasMany('TestTableRelation');
    }

    public function testTableAnothers()
    {
        return $this->hasMany('TestTableAnother');
    }
}
