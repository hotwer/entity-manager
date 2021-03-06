<?php

class Collection
{

    private $class;
    private $list;
    private $size;


    public function __construct($class)
    {
        $this->class = $class;
        $this->list = array();
        $this->size = 0;
    }

    public function last()
    {
        if ($this->size > 0) {
            $last = $this->list[$this->size - 1];
        } else {
            $last = null;
        }

        return $last;
    }

    public function index($index)
    {
        if ($this->size > $index) {
            return $this->list[$index];
        } else {
            return null;
        }
    }

    public function pushInfront($element)
    {
        return $this->push($element, 0);
    }

    public function push($element, $position = null)
    {
        $element_class = get_class($element);

        if ($element_class === false) {
            $element_class = gettype($element);
        }

        if ($element_class !== $this->class) {
            throw new ErrorException("Can't insert $element_class into a $this->class Collection");
        }

        if (!is_null($position)) {
            if ($position >= $this->size) {
                throw new ErrorException("The collection has not this many position");
            } else {
                for ($index = $this->size - 1; $index >= $position; $index--) {
                    $this->list[$index + 1] = $this->list[$index];
                }
                $this->list[$position] = $element;
                $this->size += 1;
            }
        } else {
            $this->list[$this->size++] = $element;
        }

        return $this;
    }

    public function pop($position = null)
    {
        if ($this->size > 0) {
            if (is_null($position)) {
                $element = $this->list[$this->size - 1];
                unset($this->list[$this->size--]);
            } else {
                if ($position < $this->size) {
                    $element = $this->list[$position];
                    unset($this->list[$position]);
                    $this->size -= 1;
                    $this->list = array_values($this->list);
                } else {
                    throw new ErrorException("Can't pop an empty position from collection");
                }
            }

        } else {
            throw new ErrorException('Can\'t pop from empty collection');
        }

        return $element;
    }

    public function sort($field, $flag = 'DESC')
    {
        if (is_string($field)) {
            $this->sortByField($field, $flag);
        } else {
            throw new ErrorException('$fields argument must be a string');
        }

        return $this;
    }

    private function sortByField($field, $flag)
    {
        $this->list = self::sorter($this->list, $field, $flag);
    }

    private static function sorter($elements, $field, $flag)
    {
        if (sizeof($elements) < 2) {
            return $elements;
        }

        $left = $right = array();
        reset($elements);
        $pivot_key = key($elements);
        $pivot = array_shift($elements);
        foreach ($elements as $k => $v) {
            if (self::compare($v, $pivot, $field, $flag)) {
                $right[$k] = $v;
            } else {
                $left[$k] = $v;
            }
        }
        return array_merge(self::sorter($left, $field, $flag), array($pivot_key => $pivot),
            self::sorter($right, $field, $flag));
    }

    private static function compare($first_element, $second_element, $field, $flag)
    {

        $type = array(
            'first' => gettype($first_element),
            'second' => gettype($second_element)
        );

        if ($type['first'] !== $type['second']) {
            throw new ErrorException('Comparing different types: ' . $type['first'] . ', ' . $type['second']);
        }

        switch ($type['first']) {
            case "boolean":
                if ($flag === 'ASC' || $flag === 'ASCENDANT') {
                    if ($first_element) {
                        $evaluation = true;
                    } else {
                        if ($second_element) {
                            $evaluation = false;
                        } else {
                            $evaluation = true;
                        }
                    }
                } else {
                    if ($first_element) {
                        $evaluation = false;
                    } else {
                        if ($second_element) {
                            $evaluation = true;
                        } else {
                            $evaluation = false;
                        }
                    }
                }
                break;
            case "integer":
            case "double":
                if ($flag === 'ASC' || $flag === 'ASCENDANT') {
                    $evaluation = ($first_element >= $second_element);
                } else {
                    $evaluation = ($first_element < $second_element);
                }
                break;
            case "string":
                if ($flag === 'ASC' || $flag === 'ASCENDANT') {
                    $evaluation = (strcmp($first_element, $second_element) >= 0);
                } else {
                    $evaluation = (strcmp($first_element, $second_element) < 0);
                }
                break;
            case "array":
                $evaluation = self::compare($first_element[$field], $second_element[$field], '', $flag);
                break;
            case "object":
            case "resource":
                $evaluation = self::compare($first_element->$field, $second_element->$field, '', $flag);
                break;
            case "NULL":
            default:
                $evaluation = true;
        }

        return $evaluation;
    }

    public function reverse()
    {
        if ($this->size > 0) {
            $reversed_list = array();
            for ($index = $this->size - 1; $index >= 0; $index--) {
                array_push($reversed_list, $this->list[$index]);
            }
            $this->list = $reversed_list;
        }
        return $this;
    }

    public function size()
    {
        return $this->size;
    }

    public function filter($value, $field = 'id')
    {
        return $this->find($value, $field);
    }

    public function find($value, $field = 'id')
    {
        $elements_found = array();
        if (get_class($this->first()) !== false) {
            foreach ($this->list as $element) {
                if ($value === $element->$field) {
                    array_push($elements_found, $element);
                }
            }
        } else {
            foreach ($this->list as $element) {
                if ($value === $element) {
                    array_push($elements_found, $element);
                }
            }
        }

        if (sizeof($elements_found) === 0) {
            $elements = new self($this->class);
        } else {
            if (sizeof($elements_found) < 2) {
                $elements = $elements_found[0];
            } else {
                $elements = self::fromArray($elements_found);
            }
        }

        return $elements;
    }

    public function first()
    {
        if ($this->size > 0) {
            $first = $this->list[0];
        } else {
            $first = null;
        }

        return $first;
    }

    public static function fromArray($elements)
    {
        $first_element = reset($elements);
        $element_type = gettype($first_element);

        if ($element_type === 'object' || $element_type === 'Resource') {
            $collection = new self(get_class($first_element));
        } else {
            $collection = new self($element_type);
        }

        foreach ($elements as $element) {
            $collection->push($element);
        }

        return $collection;
    }

    public function asArray()
    {
        return $this->list;
    }

    public function iterate($callback)
    {
        foreach ($this->list as $index => $element) {
            $callback($element, $index);
        }
        return $this;
    }

}
