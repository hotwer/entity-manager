<?php
class DatabaseTalkerTest extends PHPUnit_Framework_TestCase
{
    protected $connection;

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

    public function testSelectBuilder()
    {
        # test _select function
        $this->markTestIncomplete();
    }   

    public function testQueryBuilderWhereCaseCommon()
    {
        # test get_where_query_piece for normal case [field => value]
        $this->markTestIncomplete();
    }

    public function testQueryBuilderWhereCaseAnd()
    {
        # test get_where_query_piece for `and` case [field => value, "AND" => [field2 => value2]]
        $this->markTestIncomplete();
    }

    public function testQueryBuilderWhereCaseOr()
    {
        # test get_where_query_piece for `or` case [field => value, "OR" => [field2 => value2]]
        $this->markTestIncomplete();
    }

    public function testQueryBuilderWhereCaseSpecificComparison()
    {
        # test get_where_query_piece for `specific comparison` case [field => ["<>" => value]]
        $this->markTestIncomplete();
    }

    public function testQueryBuilderWhereCaseManySpecificComparison()
    {
        # test get_where_query_piece for `specific comparison` case 
        # [field => ["<>" => value], "AND" => [field2 => ["<>" => value]]]
        $this->markTestIncomplete();
    }

    public function testQueryBuilderTableJoinCaseUsing()
    {
        # test get_join_query_piece for using case `JOIN table USING (field)`
        $this->markTestIncomplete();
    }

    public function testQueryBuilderTableJoinCaseSameId()
    {
        # test get_join_query_piece for on case 
        # `JOIN table ON (table.field === table_join.field)`
        $this->markTestIncomplete();
    }

    public function testQueryBuilderTableJoinCaseDifferentId()
    {
        # test get_join_query_piece for on case 
        # `JOIN table ON (table.field === table_join.another_field)`
        $this->markTestIncomplete();
    }   

    public function testQueryBuilderTableJoinCaseManyLocks()
    {
        # test get_join_query_piece for on case 
        # `JOIN table ON (table.field === table_join.another_field AND 
        # table.plus_field === table_join.field_a)`
        $this->markTestIncomplete();
    }

    public function testInsertBuilder()
    {
        # test _insert function
        $this->markTestIncomplete();
    }

    public function testUpdateBuilder()
    {
        # test _update function
        $this->markTestIncomplete();
    }

    public function testDeleteBuilder()
    {
        # test _delete function
        $this->markTestIncomplete();
    }
}
