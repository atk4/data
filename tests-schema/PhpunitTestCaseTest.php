<?php

declare(strict_types=1);

namespace atk4\schema\tests;

use atk4\schema\PhpunitTestCase;

class PhpunitTestCaseTest extends PhpunitTestCase
{
    public function testInit()
    {
        $this->setDb($q = [
            'user' => [
                ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Steve', 'surname' => 'Jobs'],
            ],
        ]);

        $q2 = $this->getDb('user');

        $this->setDb($q2);
        $q3 = $this->getDb('user');

        $this->assertSame($q2, $q3);

        $this->assertSame($q, $this->getDb('user', true));
    }
}
