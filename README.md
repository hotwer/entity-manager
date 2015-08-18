SCEWeb Custom EntityManager ~ v0.5.3

Demands a defintion of global constants (for **DatabaseTalker**):
	DB_HOSTNAME, DB_DATABASE, DB_USERNAME, DB_PASSWORD

v0.5.3

- Fixed incompatility with PHP 5.3.8, in DatabaseTalker::hasArrayInMultiArrray uitility method, was passing function return as reference instead of variable.


v0.5.2

- Fixed problem where if the relation key where in the primary key (composite) the relation worked as if it had a pivot table.


v0.5.1

- Collection class now has unity tests for it's purpose (yes, everything is fine and dending now) 
- Fixed some functionlities with `sort` method

v0.5.0

- Created new class to deal with database querying and added new documentation

- New class to deal with database querying: DatabaseTalker!

- Database connection depency is now ONLY on DatabaseTalker (can reutilize connections as well) 


v0.3.6

- Fixed some fields that still where possible to be refeered as reserved words while quering
- Fixed possible issue where asserting an empty array (to clear the assertions) could cause some error.
- Add new function assertAdd, to assert new relations without destroing old ones.

v0.3.5

- New Features
    - New method `whereRaw` for creating raw "where" queries
    - New method findByIdOrFail to maintain the last behaviour of throwing an error when not finding the entry

- Fixed issues:
    - 'Time' fields weren't been parsed and saved correctly

- Added new tests:
    - Veryfing field types for dates/datetimes/timestamps
        - If they save correctly
        - If they are parsed correctly
    - for new method `whereRaw`
    - for new functionality of findById (returns null value instead of throwing an error)


v0.2.2

- Fixed issue where if could get query errors when using column names of reserved words.


v0.2.1

- Fixed some issues
- Implemented tests for deleting methods


v0.2.0

- Added two new methods for deleting
    - destroy($id *required*, $connection => default is NULL)
        - Required $id, and can recieve and outter PDO connection (for perfomance purposes)
    - delete
        - Deletes the instance from database (if it was saved)


v0.1.0

- Rewriteded code to use [PSR-1](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md) and [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md) conventions (with also some custom conventions as well)
    - All porpertiy variables as scoped variables MUST be named using `underscore` convention


v0.0.26

- Documenetation fix 


v0.0.25

- Added documentation for a starting guide. 


v0.0.2:

- Customized table name (just define a table name on the protected attribute $table for the table name value)

- Customized primary key name ( just define a primary key name as string or composite primary key as an array of strings)

- Now supports composite primary keys (they must not be auto increment)


v0.0.1

- No documentation