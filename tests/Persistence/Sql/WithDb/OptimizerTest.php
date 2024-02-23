<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql\WithDb;

use Atk4\Data\Persistence\Sql\Optimizer\Util;
use Atk4\Data\Schema\TestCase;

class OptimizerTest extends TestCase
{
    public function testUtilParseIdentifier(): void
    {
        self::assertSame([null, 'a'], Util::tryParseIdentifier('a'));
        self::assertSame([null, 'a'], Util::tryParseIdentifier('"a"'));
        self::assertSame([null, 'a'], Util::tryParseIdentifier($this->getConnection()->expr('{}', ['a'])));
        self::assertSame(['a', 'b'], Util::tryParseIdentifier('a.b'));
        self::assertSame(['a', 'b'], Util::tryParseIdentifier('"a".b'));
        self::assertSame(['a', 'b'], Util::tryParseIdentifier('"a"."b"'));
        self::assertSame(['a', 'b'], Util::tryParseIdentifier($this->getConnection()->expr('{}.{}', ['a', 'b'])));
        self::assertFalse(Util::tryParseIdentifier('a b'));
        self::assertFalse(Util::tryParseIdentifier('*'));
        self::assertFalse(Util::tryParseIdentifier('(a)'));

        self::assertTrue(Util::isSingleIdentifier('a'));
        self::assertTrue(Util::isSingleIdentifier('"a"'));
        self::assertTrue(Util::isSingleIdentifier($this->getConnection()->expr('{}', ['a'])));
        self::assertFalse(Util::isSingleIdentifier('a.b'));
        self::assertFalse(Util::isSingleIdentifier('"a".b'));
        self::assertFalse(Util::isSingleIdentifier('"a"."b"'));
        self::assertFalse(Util::isSingleIdentifier($this->getConnection()->expr('{}.{}', ['a', 'b'])));
        self::assertFalse(Util::isSingleIdentifier('a b'));
        self::assertFalse(Util::isSingleIdentifier('*'));
        self::assertFalse(Util::isSingleIdentifier('(a)'));
    }
}
