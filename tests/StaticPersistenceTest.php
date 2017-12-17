<?php

namespace atk4\data\tests;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class StaticPersistenceTest extends TestCase
{
    /**
     * Test constructor.
     */
    public function testBasicStatic()
    {
        $p = new \atk4\data\Persistence_Static(['hello', 'world']);
        $m = new \atk4\data\Model($p);

        $m->load(1);

        $this->assertEquals('world', $m['name']);
    }
}
