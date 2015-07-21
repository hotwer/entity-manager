<?php
	class Center extends EntityManager
	{
		public function circle()
		{
			return $this->belongsTo('Circle');
		}
	}