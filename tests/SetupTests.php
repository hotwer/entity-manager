<?php
/**
* @backupGlobals disabled
* @backupStaticAttributes disabled
*/
class SetupTests extends PHPUnit_Framework_TestCase
{
    public function __construct() 
    {
        try {
            $this->connection = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' .  DB_DATABASE, 
                DB_USERNAME, DB_PASSWORD
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new ErrorException('Failed to connect to the database/start it > ' . $e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->connection = null;
    }
}