<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\Mysql;
use Atk4\Data\Persistence\Sql\Query;

/**
 * @coversDefaultClass \Atk4\Data\Persistence\Sql\Expression
 */
class ExpressionTest extends AtkPhpunit\TestCase
{
    /**
     * @param mixed ...$args
     */
    public function e(...$args): Expression
    {
        return new Expression(...$args);
    }

    /**
     * Test constructor exception - wrong 1st parameter.
     *
     * @covers ::__construct
     */
    public function testConstructorException1st1(): void
    {
        $this->expectException(Exception::class);
        $this->e(null);
    }

    /**
     * Test constructor exception - wrong 1st parameter.
     *
     * @covers ::__construct
     */
    public function testConstructorException1st2(): void
    {
        $this->expectException(Exception::class);
        $this->e(false);
    }

    /**
     * Test constructor exception - wrong 2nd parameter.
     */
    public function testConstructorException2nd1(): void
    {
        $this->expectException(Exception::class);
        $this->e('hello, []', false);
    }

    /**
     * Test constructor exception - wrong 2nd parameter.
     */
    public function testConstructorException2nd2(): void
    {
        $this->expectException(Exception::class);
        $this->e('hello, []', 'hello');
    }

    /**
     * Test constructor exception - no arguments.
     */
    public function testConstructorException0arg(): void
    {
        // Template is not defined for Expression
        $this->expectException(Exception::class);
        $this->e()->render();
    }

    /**
     * Testing parameter edge cases - empty strings and arrays etc.
     *
     * @covers ::__construct
     */
    public function testConstructor1(): void
    {
        $this->assertSame(
            '',
            $this->e('')->render()
        );
    }

    /**
     * Testing simple template patterns without arguments.
     * Testing different ways how to pass template to constructor.
     *
     * @covers ::__construct
     * @covers ::escapeIdentifier
     */
    public function testConstructor2(): void
    {
        // pass as string
        $this->assertSame(
            'now()',
            $this->e('now()')->render()
        );
        // pass as array without key
        $this->assertSame(
            'now()',
            $this->e(['now()'])->render()
        );
        // pass as array with template key
        $this->assertSame(
            'now()',
            $this->e(['template' => 'now()'])->render()
        );
        // pass as array without key
        $this->assertSame(
            ':a Name',
            $this->e(['[] Name'], ['First'])->render()
        );
        // pass as array with template key
        $this->assertSame(
            ':a Name',
            $this->e(['template' => '[] Name'], ['Last'])->render()
        );
    }

    /**
     * Testing template with simple arguments.
     *
     * @covers ::__construct
     */
    public function testConstructor3(): void
    {
        $e = $this->e('hello, [who]', ['who' => 'world']);
        $this->assertSame('hello, :a', $e->render());
        $this->assertSame('world', $e->params[':a']);

        $e = $this->e('hello, {who}', ['who' => 'world']);
        $this->assertSame('hello, "world"', $e->render());
        $this->assertSame([], $e->params);
    }

    /**
     * Testing template with complex arguments.
     *
     * @covers ::__construct
     */
    public function testConstructor4(): void
    {
        // argument = Expression
        $this->assertSame(
            'hello, world',
            $this->e('hello, [who]', ['who' => $this->e('world')])->render()
        );

        // multiple arguments = Expression
        $this->assertSame(
            'hello, world',
            $this->e(
                '[what], [who]',
                [
                    'what' => $this->e('hello'),
                    'who' => $this->e('world'),
                ]
            )->render()
        );

        // numeric argument = Expression
        $this->assertSame(
            'hello, world',
            $this->e(
                '[]',
                [
                    $this->e(
                        '[what], [who]',
                        [
                            'what' => $this->e('hello'),
                            'who' => $this->e('world'),
                        ]
                    ),
                ]
            )->render()
        );

        // pass template as array
        $this->assertSame(
            'hello, world',
            $this->e(
                ['template' => 'hello, [who]'],
                ['who' => $this->e('world')]
            )->render()
        );
    }

    /**
     * @dataProvider provideNoTemplatingInSqlStringData
     */
    public function testNoTemplatingInSqlString(string $expectedStr, string $exprStr, array $exprArgs): void
    {
        $this->assertSame($expectedStr, $this->e($exprStr, $exprArgs)->render());
    }

    /**
     * @return iterable<array>
     */
    public function provideNoTemplatingInSqlStringData(): iterable
    {
        $testStrs = [];
        foreach (['\'', '"', '`'] as $enclosureChar) {
            foreach ([
                '\'[]\'',
                '\'{}\'',
                '\'{{}}\'',
                '\'[a]\'',
                '\'\\\'[]\'',
                '\'\\\\[]\'',
                '\'[\'\']\'',
                '\'\'\'[]\'',
                '\'[]\'\'\'',
            ] as $testStr) {
                $testStr = str_replace('\'', $enclosureChar, $testStr);

                yield [$testStr, $testStr, []];

                $testStrs[] = $testStr;
            }
        }

        $fullStr = implode('', $testStrs);
        yield [$fullStr, $fullStr, []];

        $fullStr = implode(' ', $testStrs);
        yield [$fullStr, $fullStr, []];

        $fullStr = implode('x', $testStrs);
        yield [$fullStr, $fullStr, []];
    }

    /**
     * Test nested parameters.
     *
     * @covers ::__construct
     * @covers ::escapeParam
     * @covers ::getDebugQuery
     */
    public function testNestedParams(): void
    {
        // ++1 and --2
        $e1 = $this->e('[] and []', [
            $this->e('++[]', [1]),
            $this->e('--[]', [2]),
        ]);

        $this->assertSame('++:a and --:b', $e1->render());

        $e2 = $this->e('=== [foo] ===', ['foo' => $e1]);

        $this->assertSame('=== ++:a and --:b ===', $e2->render());

        $this->assertSame('++:a and --:b', $e1->render());
    }

    /**
     * Tests where one expression with parameter is used within several other expressions.
     *
     * @covers ::__construct
     * @covers ::render
     */
    public function testNestedExpressions(): void
    {
        $e1 = $this->e('Hello [who]', ['who' => 'world']);

        $e2 = $this->e('[greeting]! How are you.', ['greeting' => $e1]);
        $e3 = $this->e('It is me again. [greeting]', ['greeting' => $e1]);

        $s2 = $e2->render(); // Hello :a! How are you.
        $s3 = $e3->render(); // It is me again. Hello :a

        $e4 = $this->e('[] and good night', [$e1]);
        $s4 = $e4->render(); // Hello :a and good night

        $this->assertSame('Hello :a! How are you.', $s2);
        $this->assertSame('It is me again. Hello :a', $s3);
        $this->assertSame('Hello :a and good night', $s4);
    }

    /**
     * @covers ::__toString
     */
    /*
    public function testToStringException1(): void
    {
        $e = new MyBadExpression('Hello');
        $this->expectException(Exception::class);
        $s = (string)$e;
    }
    */

    /**
     * expr() should return new Expression object and inherit connection from it.
     *
     * @covers ::expr
     */
    public function testExpr(): void
    {
        $e = $this->e(['connection' => new Mysql\Connection()]);
        $this->assertInstanceOf(Mysql\Connection::class, $e->expr()->connection);
    }

    /**
     * Fully covers escapeIdentifier method.
     *
     * @covers ::escape
     * @covers ::escapeIdentifier
     * @covers ::escapeIdentifierSoft
     */
    public function testEscape(): void
    {
        // escaping expressions
        $this->assertSame(
            '"first_name"',
            $this->callProtected($this->e(), 'escapeIdentifier', 'first_name')
        );
        $this->assertSame(
            '"123"',
            $this->callProtected($this->e(), 'escapeIdentifier', '123')
        );
        $this->assertSame(
            '"he""llo"',
            $this->callProtected($this->e(), 'escapeIdentifier', 'he"llo')
        );

        // should not escape expressions
        $this->assertSame(
            '*',
            $this->callProtected($this->e(), 'escapeIdentifierSoft', '*')
        );
        $this->assertSame(
            '"*"',
            $this->callProtected($this->e(), 'escapeIdentifier', '*')
        );
        $this->assertSame(
            '(2+2) age',
            $this->callProtected($this->e(), 'escapeIdentifierSoft', '(2+2) age')
        );
        $this->assertSame(
            '"(2+2) age"',
            $this->callProtected($this->e(), 'escapeIdentifier', '(2+2) age')
        );
        $this->assertSame(
            '"users"."first_name"',
            $this->callProtected($this->e(), 'escapeIdentifierSoft', 'users.first_name')
        );
        $this->assertSame(
            '"users".*',
            $this->callProtected($this->e(), 'escapeIdentifierSoft', 'users.*')
        );

        $this->assertSame(
            '"first_name"',
            $this->e()->escape('first_name')->render()
        );
        $this->assertSame(
            '"first""_name"',
            $this->e()->escape('first"_name')->render()
        );
        $this->assertSame(
            '"first""_name {}"',
            $this->e()->escape('first"_name {}')->render()
        );
    }

    /**
     * Fully covers escapeParam method.
     *
     * @covers ::escapeParam
     */
    public function testParam(): void
    {
        $e = new Expression('hello, [who]', ['who' => 'world']);
        $this->assertSame(
            'hello, :a',
            $e->render()
        );
        $this->assertSame(
            [':a' => 'world'],
            $e->params
        );
    }

    /**
     * @covers ::consume
     */
    public function testConsume(): void
    {
        $constants = (new \ReflectionClass(Expression::class))->getConstants();

        // few brief tests on consume
        $this->assertSame(
            '"123"',
            $this->callProtected($this->e(), 'consume', '123', $constants['ESCAPE_IDENTIFIER'])
        );
        $this->assertSame(
            ':x',
            $this->callProtected($this->e(['_paramBase' => 'x']), 'consume', 123, $constants['ESCAPE_PARAM'])
        );
        $this->assertSame(
            123,
            $this->callProtected($this->e(), 'consume', 123, $constants['ESCAPE_NONE'])
        );
        $this->assertSame(
            '(select *)',
            $this->callProtected($this->e(), 'consume', new Query())
        );

        $this->assertSame(
            'hello, "myfield"',
            $this->e('hello, []', [new MyField()])->render()
        );
    }

    /**
     * $escape_mode value is incorrect.
     *
     * @covers ::consume
     */
    public function testConsumeException1(): void
    {
        $this->expectException(Exception::class);
        $this->callProtected($this->e(), 'consume', 123, 'blahblah');
    }

    /**
     * Only Expressions or Expressionable objects may be used in Expression.
     *
     * @covers ::consume
     */
    public function testConsumeException2(): void
    {
        $this->expectException(Exception::class);
        $this->callProtected($this->e(), 'consume', new \StdClass());
    }

    /**
     * @covers ::offsetExists
     * @covers ::offsetGet
     * @covers ::offsetSet
     * @covers ::offsetUnset
     */
    public function testArrayAccess(): void
    {
        $e = $this->e('', ['parrot' => 'red', 'blue']);

        // offsetGet
        $this->assertSame('red', $e['parrot']);
        $this->assertSame('blue', $e[0]);

        // offsetSet
        $e['cat'] = 'black';
        $this->assertSame('black', $e['cat']);
        $e['cat'] = 'white';
        $this->assertSame('white', $e['cat']);

        // offsetExists, offsetUnset
        $this->assertTrue(isset($e['cat']));
        unset($e['cat']);
        $this->assertFalse(isset($e['cat']));

        // testing absence of specific key in asignment
        $e = $this->e('[], []');
        $e[] = 'Hello';
        $e[] = 'World';
        $this->assertSame(':a, :b', $e->render());

        // real-life example
        $age = $this->e('coalesce([age], [default_age])');
        $age['age'] = $this->e('year(now()) - year(birth_date)');
        $age['default_age'] = 18;
        $this->assertSame('coalesce(year(now()) - year(birth_date), :a)', $age->render());
    }

    /**
     * Test IteratorAggregate implementation.
     *
     * @covers ::getIterator
     *
     * @doesNotPerformAssertions
     */
    public function testIteratorAggregate(): void
    {
        // TODO can not test this without actual DB connection and executing expression
    }

    /**
     * Test for vendors that rely on JavaScript expressions, instead of parameters.
     *
     * @coversNothing
     */
    public function testJsonExpression(): void
    {
        $e = new JsonExpression('hello, [who]', ['who' => 'world']);

        $this->assertSame(
            'hello, "world"',
            $e->render()
        );
        $this->assertSame(
            [],
            $e->params
        );
    }

    /**
     * Test var-dump code for codecoverage.
     *
     * @covers ::__debugInfo
     *
     * @doesNotPerformAssertions
     */
    public function testVarDump(): void
    {
        $this->e('test')->__debugInfo();

        $this->e(' [nosuchtag] ')->__debugInfo();
    }

    /**
     * Test reset().
     *
     * @covers ::reset
     */
    public function testReset(): void
    {
        // reset everything
        $e = $this->e('hello, [name] [surname]', ['name' => 'John', 'surname' => 'Doe']);
        $e->reset();
        $this->assertSame(['custom' => []], $this->getProtected($e, 'args'));

        // reset particular custom/tag
        $e = $this->e('hello, [name] [surname]', ['name' => 'John', 'surname' => 'Doe']);
        $e->reset('surname');
        $this->assertSame(['custom' => ['name' => 'John']], $this->getProtected($e, 'args'));
    }
}

// @codingStandardsIgnoreStart
class JsonExpression extends Expression
{
    public function escapeParam($value): string
    {
        return json_encode($value);
    }
}
class MyField implements Expressionable
{
    public function getDsqlExpression(Expression $expr): Expression
    {
        return $expr->expr('"myfield"');
    }
}
/*
class MyBadExpression extends Expression
{
    public function getOne()
    {
        // should return string, but for test case we return array to get \Exception
        return array();
    }
}
*/
// @codingStandardsIgnoreEnd
