<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Schema\TestCase;

class TestCaseTest extends TestCase
{
    public function testInit(): void
    {
        $this->setDb($q = [
            'user' => [
                ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Steve', 'surname' => 'Jobs'],
            ],
        ]);

        $q2 = $this->getDb(['user']);

        $this->setDb($q2);
        $q3 = $this->getDb(['user']);

        $this->assertSameExportUnordered($q2, $q3);

        $this->assertSameExportUnordered($q, $this->getDb(['user'], true));
    }
}
