<?php 
	class Square extends EntityManager 
	{
		public function cube()
		{
			return $this->belongs_to('Cube');
		}
	}