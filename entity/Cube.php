<?php 
	class Cube extends EntityManager 
	{
		public function squares()
		{
			return $this->hasMany('Square');
		}
	}