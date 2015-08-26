### This is a helper class for dealing with the Entities created by the EntityManager

## Instanced methods list: ##

* first()
    * returns the first element

* last()
    * returns the last element

* push( element *required*,  int/string index )
    * inserts a new element into the collection
    * accepts two arguments: the element to insert, and the position to where to insert

* pop( int/string index )
    * removes an element from the collection
    * accepts one argument: the index of the element to pop

* index( int/string index *required* )
    * get the element of certain index from the collection
    * accepts one argument: the index of the element to get

* size() 
    * gets the collection size

* sort (field *required*, 'ASC/DESC' => DESC is default)
    * sorts by certain field of the collection
    * accepts two arguments: the field to sort and the criteria (ascendant or descendant)

## Static methods list:
 
* fromArray( array ) -> expects a symmetric array (all containing the same structure)
    * returns a collection from this array