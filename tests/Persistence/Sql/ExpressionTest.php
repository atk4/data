<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\Mysql;
use Atk4\Data\Persistence\Sql\Sqlite;

class ExpressionTest extends TestCase
{
    /**
     * @param string|array<string, mixed> $template
     * @param array<int|string, mixed>    $arguments
     */
    protected function e($template = [], array $arguments = []): Expression
    {
        return new class($template, $arguments) extends Expression {
            protected string $identifierEscapeChar = '"';
        };
    }

    public function testConstructorNoTemplateException(): void
    {
        $this->expectException(Exception::class);
        $this->e()->render();
    }

    public function testConstructorEmptyTemplate(): void
    {
        self::assertSame(
            '',
            $this->e('')->render()[0]
        );
    }

    /**
     * Testing simple template patterns without arguments.
     * Testing different ways how to pass template to constructor.
     */
    public function testConstructor2(): void
    {
        // pass as string
        self::assertSame(
            'now()',
            $this->e('now()')->render()[0]
        );
        // pass as array with template key
        self::assertSame(
            'now()',
            $this->e(['template' => 'now()'])->render()[0]
        );
        // pass as array with template key
        self::assertSame(
            ':a Name',
            $this->e(['template' => '[] Name'], ['Last'])->render()[0]
        );
    }

    /**
     * Testing template with simple arguments.
     */
    public function testConstructor3(): void
    {
        $e = $this->e('hello, [who]', ['who' => 'world']);
        self::assertSame([
            'hello, :a',
            [':a' => 'world'],
        ], $e->render());

        $e = $this->e('hello, {who}', ['who' => 'world']);
        self::assertSame([
            'hello, "world"',
            [],
        ], $e->render());
    }

    /**
     * Testing template with complex arguments.
     */
    public function testConstructor4(): void
    {
        // argument = Expression
        self::assertSame(
            'hello, world',
            $this->e('hello, [who]', ['who' => $this->e('world')])->render()[0]
        );

        // multiple arguments = Expression
        self::assertSame(
            'hello, world',
            $this->e(
                '[what], [who]',
                [
                    'what' => $this->e('hello'),
                    'who' => $this->e('world'),
                ]
            )->render()[0]
        );

        // numeric argument = Expression
        self::assertSame(
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
            )->render()[0]
        );

        // pass template as array
        self::assertSame(
            'hello, world',
            $this->e(
                ['template' => 'hello, [who]'],
                ['who' => $this->e('world')]
            )->render()[0]
        );
    }

    /**
     * @param array<int|string, mixed> $exprArguments
     *
     * @dataProvider provideNoTemplatingInSqlStringCases
     */
    public function testNoTemplatingInSqlString(string $expectedStr, string $exprTemplate, array $exprArguments): void
    {
        self::assertSame($expectedStr, $this->e($exprTemplate, $exprArguments)->render()[0]);
    }

    /**
     * @return iterable<list<mixed>>
     */
    public function provideNoTemplatingInSqlStringCases(): iterable
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

    public function testNestedParams(): void
    {
        $e1 = $this->e('[] and []', [
            $this->e('++[]', [1]),
            $this->e('--[]', [2]),
        ]);

        self::assertSame('++:a and --:b', $e1->render()[0]);

        $e2 = $this->e('=== [foo] ===', ['foo' => $e1]);

        self::assertSame('=== ++:a and --:b ===', $e2->render()[0]);

        self::assertSame('++:a and --:b', $e1->render()[0]);
    }

    /**
     * Tests where one expression with parameter is used within several other expressions.
     */
    public function testNestedExpressions(): void
    {
        $e1 = $this->e('Hello [who]', ['who' => 'world']);

        $e2 = $this->e('[greeting]! How are you.', ['greeting' => $e1]);
        $e3 = $this->e('It is me again. [greeting]', ['greeting' => $e1]);

        $s2 = $e2->render()[0]; // Hello :a! How are you.
        $s3 = $e3->render()[0]; // It is me again. Hello :a

        $e4 = $this->e('[] and good night', [$e1]);
        $s4 = $e4->render()[0]; // Hello :a and good night

        self::assertSame('Hello :a! How are you.', $s2);
        self::assertSame('It is me again. Hello :a', $s3);
        self::assertSame('Hello :a and good night', $s4);
    }

    public function testExpr(): void
    {
        self::assertInstanceOf(Expression::class, $this->e('foo'));

        $connection = new Mysql\Connection();
        $e = new Mysql\Expression(['connection' => $connection]);
        self::assertSame(Mysql\Expression::class, get_class($e->expr('foo')));
        self::assertSame($connection, $e->expr('foo')->connection);
    }

    public function testEscapeIdentifier(): void
    {
        // escaping expressions
        self::assertSame(
            '"first_name"',
            $this->callProtected($this->e(), 'escapeIdentifier', 'first_name')
        );
        self::assertSame(
            '"123"',
            $this->callProtected($this->e(), 'escapeIdentifier', '123')
        );
        self::assertSame(
            '"he""llo"',
            $this->callProtected($this->e(), 'escapeIdentifier', 'he"llo')
        );

        // should not escape expressions
        self::assertSame(
            '*',
            $this->callProtected($this->e(), 'escapeIdentifierSoft', '*')
        );
        self::assertSame(
            '"*"',
            $this->callProtected($this->e(), 'escapeIdentifier', '*')
        );
        self::assertSame(
            '(2 + 2) age',
            $this->callProtected($this->e(), 'escapeIdentifierSoft', '(2 + 2) age')
        );
        self::assertSame(
            '"(2 + 2) age"',
            $this->callProtected($this->e(), 'escapeIdentifier', '(2 + 2) age')
        );
        self::assertSame(
            '"users"."first_name"',
            $this->callProtected($this->e(), 'escapeIdentifierSoft', 'users.first_name')
        );
        self::assertSame(
            '"users".*',
            $this->callProtected($this->e(), 'escapeIdentifierSoft', 'users.*')
        );
    }

    public function testEscapeParam(): void
    {
        $e = $this->e('hello, [who]', ['who' => 'world']);
        self::assertSame([
            'hello, :a',
            [':a' => 'world'],
        ], $e->render());
    }

    public function testConsume(): void
    {
        $constants = (new \ReflectionClass(Expression::class))->getConstants();

        // few brief tests on consume
        self::assertSame(
            '"123"',
            $this->callProtected($this->e(), 'consume', '123', $constants['ESCAPE_IDENTIFIER'])
        );
        self::assertSame(
            ':x',
            $this->callProtected($this->e(['renderParamBase' => 'x']), 'consume', 123, $constants['ESCAPE_PARAM'])
        );
        self::assertSame(
            123,
            $this->callProtected($this->e(), 'consume', 123, $constants['ESCAPE_NONE'])
        );

        $myField = new class() implements Expressionable {
            public function getDsqlExpression(Expression $expr): Expression
            {
                return $expr->expr('"myfield"');
            }
        };
        $e = $this->e('hello, []', [$myField]);
        $e->connection = new Sqlite\Connection();
        self::assertSame(
            'hello, "myfield"',
            $e->render()[0]
        );
    }

    public function testConsumeUnsupportedEscapeModeException(): void
    {
        $this->expectException(Exception::class);
        $this->callProtected($this->e(), 'consume', 123, 'blahblah');
    }

    public function testConsumeNotExpressionableObjectException(): void
    {
        $this->expectException(\Error::class);
        $this->callProtected($this->e(), 'consume', new \stdClass());
    }

    public function testRenderNoTagException(): void
    {
        $e = $this->e('hello, [world]');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Expression could not render tag');
        try {
            $e->render();
        } catch (Exception $e) {
            self::assertSame('world', $e->getParams()['tag']);

            throw $e;
        }
    }

    public function testArrayAccess(): void
    {
        $e = $this->e('', ['parrot' => 'red', 'blue']);

        // offsetGet
        self::assertSame('red', $e['parrot']);
        self::assertSame('blue', $e[0]);

        // offsetSet
        $e['cat'] = 'black';
        self::assertSame('black', $e['cat']);
        $e['cat'] = 'white';
        self::assertSame('white', $e['cat']);

        // offsetExists, offsetUnset
        self::assertTrue(isset($e['cat']));
        unset($e['cat']);
        self::assertFalse(isset($e['cat']));

        // testing absence of specific key in assignment
        $e = $this->e('[], []');
        $e[] = 'Hello';
        $e[] = 'World';
        self::assertSame(':a, :b', $e->render()[0]);

        // real-life example
        $age = $this->e('coalesce([age], [default_age])');
        $age['age'] = $this->e('year(now()) - year(birth_date)');
        $age['default_age'] = 18;
        self::assertSame('coalesce(year(now()) - year(birth_date), :a)', $age->render()[0]);
    }

    public function testEscapeParamCustom(): void
    {
        $e = new class('hello, [who]', ['who' => 'world']) extends Expression {
            public function escapeParam($value): string
            {
                return json_encode($value);
            }
        };

        self::assertSame([
            'hello, "world"',
            [],
        ], $e->render());
    }

    public function testVarDump(): void
    {
        self::assertSame(
            'test',
            $this->e('test')->__debugInfo()['R']
        );

        self::assertStringContainsString(
            'Expression could not render tag',
            $this->e(' [nosuchtag] ')->__debugInfo()['R']
        );
    }

    public function testReset(): void
    {
        // reset everything
        $e = $this->e('hello, [name] [surname]', ['name' => 'John', 'surname' => 'Doe']);
        $e->reset();
        self::assertSame(['custom' => []], $this->getProtected($e, 'args'));

        // reset particular custom/tag
        $e = $this->e('hello, [name] [surname]', ['name' => 'John', 'surname' => 'Doe']);
        $e->reset('surname');
        self::assertSame(['custom' => ['name' => 'John']], $this->getProtected($e, 'args'));
    }
}
