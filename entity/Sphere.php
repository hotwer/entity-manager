<?php
	class Sphere extends EntityManager
	{
		public function circles()
		{
			return $this->hasMany('Circle');
		}
	}