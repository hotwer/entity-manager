<?php
	class TestTableAnother extends EntityManager 
	{
		public function test_tables() 
		{
			return $this->belongs_to_many('TestTable');
		} 
	}