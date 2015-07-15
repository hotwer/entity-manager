<?php
	class Center extends EntityManager
	{
		public function circle()
		{
			return $this->belongs_to('Circle');
		}
	}