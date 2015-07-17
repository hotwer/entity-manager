<?php
	class CustomizedTable extends EntityManager {
		protected $table = 'my_custom_table';

		protected $primary_key = 'custom_id';
	}