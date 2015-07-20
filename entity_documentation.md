Hello, developer.

This is the EntityManager developed by SCEWeb Company.
The idea is to have a totally a part objected-oriented database management.

 **It only supports MySQL databases at the moment.**

## Setup ##
First you need to setup your enviroment. We'd suggest an AutoLoader to easily include any Entity you're working with.

Create a file with the following code:

```
class AutoLoader {
	 
static private $classNames = array();

public static function registerDirectory($dirName) {
	$di = new DirectoryIterator($dirName);
	foreach ($di as $file) {
		if ($file->isDir() && !$file->isLink() && !$file->isDot()) {
			self::registerDirectory($file->getPathname());
		} elseif (substr($file->getFilename(), -4) === '.php') {
			$className = substr($file->getFilename(), 0, -4);
			AutoLoader::registerClass($className, $file->getPathname());
		}
	}
}

public static function registerClass($className, $fileName) {
	AutoLoader::$classNames[$className] = $fileName;
}

public static function loadClass($className) {
	if (isset(AutoLoader::$classNames[$className])) {
		require_once(AutoLoader::$classNames[$className]);
	}
 }

}

spl_autoload_register(array('AutoLoader', 'loadClass'));

```

After that you just need to include it to your main file with config and register your folders:

```
// bootstrap.php file example:
require_once('config.php');
require_once('lib/AutoLoader.php');

AutoLoader::registerDirectory('lib');
AutoLoader::registerDirectory('core');
AutoLoader::registerDirectory('entity');
```

## Defining entities ##

To define an entity is really simple, just define your class extending the EntityManager class.

```
class MyEntity extends EntityManager 
{

}
```

Now, it automatically knows everything about your database table and your attributes.
The table name expected is lowered case undescored name of the entity name and the primary key column name expected is the table name with '_id' and the end 

```
$entity = new MyEntity(); //creates the new entity

//this attribute is already created with default db value for the 'my_entity' table 
$entity->column_name = 'Some value to insert'; 

//makes the insert/update which one is necessary
$entity->save();

//does the same with alternate syntax (a little bit less verbose)
$entity = MyEntity::create(array('column_name' => 'Some value to insert'));

```

But it's just really a convention, you can define either the name of the primary key(s) and the table as well.

```
class SomeEntity extends EntityManager
{
	protected $table = 'my_custom_tablename';
	protected $primary_key = 'my_custom_primary_key';
}
```

Right know, if your table has a composite primary key, it expects a an array to be set with the primary key names, this is mandadory

```
class MyEntity extends EntityManager
{
	protected $primary_key = array('primary_key', 'second_primary_key');
}
```

Ok, now we know how to save stuff, but how to retrieve?
It's quite simple too, we have two helper methods: `find_by_id` and `where`

```
$entity = MyEntity::find_by_id(1)
print $entity->field //prints what was in field of the entry of id '1' of database

//@returns a Collection of the entity. See the collection_documentation.md for more info.
$entity = MyEntity::where(array(field => 'my value'));

//example of a complex where

$entity = MyEntity::where(array(
	'(field_1' => 'value',
	'AND' => array('field_2' => '1)'),
	'OR' => array('field_3' => array('<>' => 3)), 
));
```



For defining relationships, its easy too, it has four helping funcitions for it: `has_one`, `belongs_to`, `has_many` and `belongs_to_many`.

This is a simple one to many relationship.

```
class MyEntity extends EntityManager
{
	public function entity_relationed
	{
		return $this->has_one('EntityRelationed');
	}
}

class EntityRelationed extends EntityManager
{
	public function entity
	{
		return $this->belongs_to_many('MyEntity');
	}
}
```

Using is simple
```
$entity = MyEntity::find_by_id(3)->entity_relationed(); //loads the entity relationed;
print $entity->entity_relationed->entity_relationed_field; //easy access to their columns

//and can also be updated
$entity->entity_relationed->update(array('field1' => 'abc', 'field_2' => 'cdi'));
```


And working with pivot tables (many to many relationship)?

Well, right now it's a little hard, to make it easier we've defined some conventions.
(We're working on customization for it right away, we must not have performance problems)

Well, at first we expect a table with this convention name
`has_many_table_name**_to_**belongs_to_many_table_name`

After the convention, defining it in the entity is a simple task

```
class HasManyTableName extends EntityManager
{
	public function belongs_to_many_table_names()
	{
		return $this->has_many('BelongsToManyTableName');
	}
}

class BelongsToManyTableName extends EntityManager
{
	public function has_many_table_names()
	{
		return $this->belongs_to_many('HasManyTableName');
	}
}
```

And the use is the same

```
//loads a collection of entities since it could be more than one
$has_many_entity = HasManyTableName::find_by_id(11)->belongs_many_table_names();

print $has_many_entity->belongs_to_many_table_name->index(0)->field_after_pivot_table;

//to save any ids you want to relate, it's easy as well
$has_many_entity->assert(array(1, 2, 3, 4));

//or if its has a composite primary key
$has_many_entity->assert(array(
	array('key1' => 1, 'key2' => 2), 
	array('key1' => 3, 'key2' => 4),
));

```

And if the pivot table has any field?
Well, in this case, we are not supporting this definition as a pivot table.
It cant a throught relationship

HasMany -> some_table <- BelongsToMany

It's an entity for itself, since it has it's own values.
But we're working in a way to define this kind of pivot table for a more flexible approach.\


## Performance Aspects ##

Since it's an 'atomic' class, and works for itself, and also has a flexible enviroment to work,
every time it instantiates an entity, it creates a new connection to the database.

To supress this problem, every instatiation function has some way to pass a connection through parameters, so it uses an already open connection.

**It only supports PDO connections.**

```
$connection = new PDO(...);

$entity = new MyEntity(array('connection' => $connection));
$entity = MyEntity::all();
$entity = MyEntity::find_by_id(2, $connection);
$entity = MyEntity::where(array('field' => array('LIKE' => 'value%')), $connection);
$entity = MyEntity::create(array('field' => 'new inserted value'), $connection);

//you can also, if you're not going to need any connection you can pass false as well 
//(like getting the table name, primary key(s) or w/e)
$entity = new MyEntity(array('connection' => false))
```

## Complex Approach ##

In certain cases, you need to go deep into querying to the database, but we do not recommend the use of these functions (`_select`, `_update`, `_insert` and `_update`). The all work alone, and serves for purpose of querying, but you can learn about then and use as well we just don't recommend if you don't know what you're doing. 

```
$entity = new MyEntity();
//returns all elements from `my_entity` table ordered by `some_field`
$elements = $entity->_select(array('order_by' => 'some_field'));
```

For tottaly custom queries we have these two methods `retieve` and `query`;

```
$entity = new EntityManager();

//also returns all elements from `my_entity` table ordered by `some_field`
$elements = $entity->retrive('SELECT * FROM my_entity ORDER BY `some_field`');

//user retrive for fetching content from your database, like select queries or
$process_list = $entity->retrive('DISPLAY PROCESSLIST');

//or if you want to update or insert (its procedural, but throws any error if given)
$entity->query('INSERT INTO my_entity (`some_field`, `another_field`) VALUES (?, ?), (?, ?)', array('value', 'value1', 'another value', 'another value2'));
```



