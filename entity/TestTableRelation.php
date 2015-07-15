<?php
	class TestTableRelation extends EntityManager
	{
		public function test_table() 
		{
			return $this->belongs_to('TestTable');
		}
	}