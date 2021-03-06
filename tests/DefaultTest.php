<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;

class DefaultTest extends \Atk4\Schema\PhpunitTestCase
{
    public function testDefaultValue(): void
    {
        $m = new Model();
        $m->addField('nodefault');
        $m->addField('withdefault', ['default' => 'abc']);
        $m = $m->createEntity();

        $this->assertNull($m->get('nodefault'));
        $this->assertSame('abc', $m->get('withdefault'));

        $this->assertNull($m->getField('nodefault')->get());
        $this->assertSame('abc', $m->getField('withdefault')->get());
    }
}
