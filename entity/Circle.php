<?php 
	class Circle extends EntityManager 
	{
		public function center()
		{
			return $this->has_one('Center');
		}

		public function spheres()
		{
			return $this->belongs_to_many('Sphere');
		}
	}