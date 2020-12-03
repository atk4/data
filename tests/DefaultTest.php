<?php

declare(strict_types=1);

namespace atk4\data\Tests;

use atk4\data\Model;

class DefaultTest extends \atk4\schema\PhpunitTestCase
{
    public function testDefaultValue()
    {
        $m = new Model();
        $m->addField('nodefault');
        $m->addField('withdefault', ['default' => 'abc']);

        $this->assertNull($m->get('nodefault'));
        $this->assertSame('abc', $m->get('withdefault'));

        $this->assertNull($m->getField('nodefault')->get());
        $this->assertSame('abc', $m->getField('withdefault')->get());
    }
}
