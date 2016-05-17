<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Field;

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
        $m->addField('name');

        $f = $m->getElement('name');
        $this->assertEquals('name', $f->short_name);

        $m->add(new Field(), 'surname');
        $f = $m->getElement('surname');
        $this->assertEquals('surname', $f->short_name);
    }

}
