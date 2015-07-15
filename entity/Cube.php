<?php 
	class Cube extends EntityManager 
	{
		public function squares()
		{
			return $this->has_many('Square');
		}
	}