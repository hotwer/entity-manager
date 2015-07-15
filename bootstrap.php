<?php
	require_once('config.php');
	require_once('lib/AutoLoader.php');

	AutoLoader::registerDirectory('lib');
	AutoLoader::registerDirectory('core');
	AutoLoader::registerDirectory('entity');

	$test_tables_data_set = Spyc::YAMLLoad("setup/TestTablesDataSet.yml");

	$connection = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' .  DB_DATABASE,
	DB_USERNAME, DB_PASSWORD);

	foreach($test_tables_data_set as $table => $data_set)
	{
		$connection->query("TRUNCATE TABLE $table;");
		$insert_query = "INSERT INTO $table (".implode(array_keys(reset($data_set)), ',').") VALUES ";
		foreach ($data_set as $data)
			$insert_query .= "('".implode($data, "','")."'),";
		$insert_query = rtrim($insert_query, ','); 
		$connection->query($insert_query);
	}

	$connection = null;

