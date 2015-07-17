<?php
	class TableWithCompositeKey extends EntityManager
	{
		protected $primary_key = array('first_key', 'second_key');
	}