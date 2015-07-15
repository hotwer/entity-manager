<?php
	class Sphere extends EntityManager
	{
		public function circles()
		{
			return $this->has_many('Circle');
		}
	}