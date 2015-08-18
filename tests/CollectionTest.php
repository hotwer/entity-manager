<?php
class CollectionTest extends SetupTests
{
    public function testFirst()
    {
        $items = Item::all($this->connection);

        $this->assertEquals('one', $items->first()->string);
        $this->assertEquals(10, $items->first()->integer);
        $this->assertEquals(true, $items->first()->boolean);
        $this->assertEquals(500.70, $items->first()->float);
        $this->assertEquals(32.06, $items->first()->decimal);
    }

    public function testLast()
    {
        $items = Item::all($this->connection);

        $this->assertEquals('two', $items->last()->string);
        $this->assertEquals(9, $items->last()->integer);
        $this->assertEquals(false, $items->last()->boolean);
        $this->assertEquals(600.10, $items->last()->float);
        $this->assertEquals(32.09, $items->last()->decimal);
    }

    public function testSize()
    {
        $items = Item::all($this->connection);
        $this->assertEquals(4, $items->size());
    }

    public function testPush()
    {
        $items = Item::all($this->connection);
        
        $items->push(Item::create(array(
            'string' => 'three',
            'integer' => 12,
            'boolean' => false,
            'float' => 700.01,
            'decimal' => 33,
        )));

        $this->assertEquals('three', $items->last()->string);
        $this->assertEquals(12, $items->last()->integer);
        $this->assertEquals(false, $items->last()->boolean);
        $this->assertEquals(700.01, $items->last()->float);
        $this->assertEquals(33, $items->last()->decimal);

        $this->assertEquals(5, $items->size());

        $items->last()->delete();
    }

    public function testPop()
    {   
        $items = Item::all($this->connection);

        $popped_item = $items->pop();

        $this->assertEquals('two', $popped_item->string);
        $this->assertEquals(9, $popped_item->integer);
        $this->assertEquals(false, $popped_item->boolean);
        $this->assertEquals(600.10, $popped_item->float);
        $this->assertEquals(32.09, $popped_item->decimal);

        $this->assertEquals(3, $items->size());

        $this->assertEquals('two', $items->last()->string);
        $this->assertEquals(10, $items->last()->integer);
        $this->assertEquals(true, $items->last()->boolean);
        $this->assertEquals(500.60, $items->last()->float);
        $this->assertEquals(32.08, $items->last()->decimal);
    }

    public function testPushInfront()
    {
        $items = Item::all($this->connection);
        
        $items->pushInfront(Item::create(array(
            'string' => 'three',
            'integer' => 12,
            'boolean' => false,
            'float' => 700.01,
            'decimal' => 33,
        )));

        $this->assertEquals('three', $items->first()->string);
        $this->assertEquals(12, $items->first()->integer);
        $this->assertEquals(false, $items->first()->boolean);
        $this->assertEquals(700.01, $items->first()->float);
        $this->assertEquals(33, $items->first()->decimal);

        $this->assertEquals(5, $items->size());

        $items->first()->delete();
    }

    public function testSortByString()
    {
        $items = Item::all($this->connection);

        $context = $this;
        $items->sort('string', 'ASC')->iterate(function($item, $index) use ($context)
        {
            switch ($index) 
            {
                case 0:
                case 1:
                    $context->assertEquals('one', $item->string);
                    break;
                case 2:
                case 3:
                    $context->assertEquals('two', $item->string);
                    break;
            }
        });

        $items->sort('string')->iterate(function($item, $index) use ($context)
        {
            switch ($index) 
            {
                case 0:
                case 1:
                    $context->assertEquals('two', $item->string);
                    break;
                case 2:
                case 3:
                    $context->assertEquals('one', $item->string);
                    break;
            }
        });
    }

    public function testSortByInteger()
    {
        $items = Item::all($this->connection);

        $context = $this;

        $items->sort('integer', 'ASC')->iterate(function($item, $index) use ($context)
        {
            switch ($index) 
            {
                case 0:
                    $context->assertEquals(9, $item->integer);
                    break;
                case 1:
                case 2:
                    $context->assertEquals(10, $item->integer);
                    break;
                case 3:
                    $context->assertEquals(11, $item->integer);
                    break;
            }
        });

        $items->sort('integer')->iterate(function($item, $index) use ($context)
        {
            switch ($index) 
            {
                case 0:
                    $context->assertEquals(11, $item->integer);
                    break;
                case 1:
                case 2:
                    $context->assertEquals(10, $item->integer);
                    break;
                case 3:
                    $context->assertEquals(9, $item->integer);
                    break;
            }
        });
    }

    public function testSortByBoolean()
    {
        $items = Item::all($this->connection);

        $context = $this;

        $items->sort('boolean', 'ASC')->iterate(function($item, $index) use ($context)
        {
            switch ($index) 
            {
                case 0:
                case 1:
                    $context->assertEquals(false, $item->boolean);
                    break;
                case 2:
                case 3:
                    $context->assertEquals(true, $item->boolean);
                    break;
            }
        });

        $items->sort('boolean')->iterate(function($item, $index)  use ($context)
        {
            switch ($index) 
            {
                case 0:
                case 1:
                    $context->assertEquals(true, $item->boolean);
                    break;
                case 2:
                case 3:
                    $context->assertEquals(false, $item->boolean);
                    break;
            }
        });
    }

    public function testSortByFloat()
    {
        $items = Item::all($this->connection);

        $context = $this;

        $items->sort('float', 'ASC')->iterate(function($item, $index)  use ($context)
        {
            switch ($index) 
            {
                case 0:
                    $context->assertEquals(500.60, $item->float);
                    break;
                case 1:
                    $context->assertEquals(500.70, $item->float);
                    break;
                case 2:
                case 3:
                    $context->assertEquals(600.10, $item->float);
                    break;
            }
        });

        $items->sort('float')->iterate(function($item, $index)  use ($context)
        {
            switch ($index) 
            {
                case 0:
                case 1:
                    $context->assertEquals(600.10, $item->float);
                    break;
                case 2:
                    $context->assertEquals(500.70, $item->float);
                    break;
                case 3:
                    $context->assertEquals(500.60, $item->float);
                    break;
            }
        });
    }

    public function testSortByDecimal()
    {
        $items = Item::all($this->connection);

        $context = $this;

        $items->sort('decimal', 'ASC')->iterate(function($item, $index)  use ($context)
        {
            switch ($index) 
            {
                case 0:
                    $context->assertEquals(32.06, $item->decimal);
                    break;
                case 1:
                    $context->assertEquals(32.07, $item->decimal);
                    break;
                case 2:
                    $context->assertEquals(32.08, $item->decimal);
                    break;
                case 3:
                    $context->assertEquals(32.09, $item->decimal);
                    break;
            }
        });

        $items->sort('decimal')->iterate(function($item, $index)  use ($context)
        {
            switch ($index) 
            {
                case 0:
                    $context->assertEquals(32.09, $item->decimal);
                    break;
                case 1:
                    $context->assertEquals(32.08, $item->decimal);
                    break;
                case 2:
                    $context->assertEquals(32.07, $item->decimal);
                    break;
                case 3:
                    $context->assertEquals(32.06, $item->decimal);
                    break;
            }
        });
    }

    public function testIndex()
    {
        $items = Item::all($this->connection);

        $this->assertEquals('one', $items->index(0)->string);
        $this->assertEquals(10, $items->index(0)->integer);
        $this->assertEquals(true, $items->index(0)->boolean);
        $this->assertEquals(500.70, $items->index(0)->float);
        $this->assertEquals(32.06, $items->index(0)->decimal);

        $this->assertEquals('two', $items->index(3)->string);
        $this->assertEquals(9, $items->index(3)->integer);
        $this->assertEquals(false, $items->index(3)->boolean);
        $this->assertEquals(600.10, $items->index(3)->float);
        $this->assertEquals(32.09, $items->index(3)->decimal);
    }
}
