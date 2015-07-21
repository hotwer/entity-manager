<?php
	class TestTable extends EntityManager 
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