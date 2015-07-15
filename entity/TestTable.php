<?php
	class TestTable extends EntityManager 
	{
		public function test_table_relations() 
		{
			return $this->has_many('TestTableRelation');
		}
		
		public function test_table_anothers() 
		{
			return $this->has_many('TestTableAnother');
		}
	}