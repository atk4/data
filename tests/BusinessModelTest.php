<?php
namespace atk4\data\tests;

use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class BusinessModelTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Test constructor
     *
     */
    public function testConstruct()
    {
        $m = new Model();
        $m->addField('test');
    }

}
