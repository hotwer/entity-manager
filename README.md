SCEWeb Custom EntityManager ~ v0.2.1

Demands a defintion of global constants:
	DB_HOSTNAME, DB_DATABASE, DB_USERNAME, DB_PASSWORD

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

- Customized primary key name (just define a primary key name as string or composite primary key as an array of strings)

- Now supports composite primary keys (they must not be auto increment)


v0.0.1

- No documentation