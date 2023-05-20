<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class LimitOrderTest extends TestCase
{
    public function testBasic(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice', 'idField' => false]);
        $i->addField('total_net', ['type' => 'integer']);
        $i->addField('total_vat', ['type' => 'integer']);
        $i->addExpression('total_gross', ['expr' => '[total_net] + [total_vat]', 'type' => 'integer']);

        $i->setOrder('total_net');
        $i->setOnlyFields(['total_net']);
        self::assertSame([
            ['total_net' => 10],
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());
    }

    public function testReverse(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 5], // total_gross 15
                ['total_net' => 10, 'total_vat' => 4], // total_gross 14
                ['total_net' => 15, 'total_vat' => 4], // total_gross 19
            ],
        ]);

        $ii = new Model($this->db, ['table' => 'invoice', 'idField' => false]);
        $ii->addField('total_net', ['type' => 'integer']);
        $ii->addField('total_vat', ['type' => 'integer']);
        $ii->addExpression('total_gross', ['expr' => '[total_net] + [total_vat]', 'type' => 'integer']);

        $i = clone $ii;
        $i->setOrder(['total_net' => 'desc', 'total_gross' => 'desc']);
        $i->setOnlyFields(['total_net', 'total_gross']);
        self::assertSame([
            ['total_net' => 15, 'total_gross' => 19],
            ['total_net' => 10, 'total_gross' => 15],
            ['total_net' => 10, 'total_gross' => 14],
        ], $i->export());

        $i = clone $ii;
        $i->setOrder(['total_net' => 'desc', 'total_gross']);
        $i->setOnlyFields(['total_net', 'total_gross']);
        self::assertSame([
            ['total_net' => 15, 'total_gross' => 19],
            ['total_net' => 10, 'total_gross' => 14],
            ['total_net' => 10, 'total_gross' => 15],
        ], $i->export());

        $i = clone $ii;
        $i->setOrder(['total_net' => 'desc', 'total_gross']);
        $i->setOnlyFields(['total_net', 'total_vat']);
        self::assertSame([
            ['total_net' => 15, 'total_vat' => 4],
            ['total_net' => 10, 'total_vat' => 4],
            ['total_net' => 10, 'total_vat' => 5],
        ], $i->export());

        $i = clone $ii;
        $i->setOrder(['total_gross' => 'desc', 'total_net']);
        $i->setOnlyFields(['total_net', 'total_vat']);
        self::assertSame([
            ['total_net' => 15, 'total_vat' => 4],
            ['total_net' => 10, 'total_vat' => 5],
            ['total_net' => 10, 'total_vat' => 4],
        ], $i->export());
    }

    public function testArrayParameters(): void
    {
        $this->setDb([
            'invoice' => [
                ['net' => 10, 'vat' => 5],
                ['net' => 10, 'vat' => 4],
                ['net' => 15, 'vat' => 4],
            ],
        ]);

        $ii = new Model($this->db, ['table' => 'invoice', 'idField' => false]);
        $ii->addField('net', ['type' => 'integer']);
        $ii->addField('vat', ['type' => 'integer']);

        // pass parameters as array elements [field, order]
        $i = clone $ii;
        $i->setOrder([['net', 'desc'], ['vat']]);
        $i->setOnlyFields(['net', 'vat']);
        self::assertSame([
            ['net' => 15, 'vat' => 4],
            ['net' => 10, 'vat' => 4],
            ['net' => 10, 'vat' => 5],
        ], $i->export());

        // pass parameters as array elements [field => order]
        $i = clone $ii;
        $i->setOrder(['net' => 'desc', 'vat' => 'asc']);
        $i->setOnlyFields(['net', 'vat']);
        self::assertSame([
            ['net' => 15, 'vat' => 4],
            ['net' => 10, 'vat' => 4],
            ['net' => 10, 'vat' => 5],
        ], $i->export());

        // pass parameters as array elements [field => order], same as above but use 'desc' instead of true
        $i = clone $ii;
        $i->setOrder(['net' => 'desc', 'vat']); // and you can even mix them (see 'vat' is a value not a key here)
        $i->setOnlyFields(['net', 'vat']);
        self::assertSame([
            ['net' => 15, 'vat' => 4],
            ['net' => 10, 'vat' => 4],
            ['net' => 10, 'vat' => 5],
        ], $i->export());
    }

    public function testOrderByExpressions(): void
    {
        $this->setDb([
            'invoice' => [
                ['code' => 'A', 'net' => 10, 'vat' => 5],
                ['code' => 'B', 'net' => 10, 'vat' => 4],
                ['code' => 'C', 'net' => 15, 'vat' => 4],
            ],
        ]);

        // order by expression field
        $i = new Model($this->db, ['table' => 'invoice', 'idField' => false]);
        $i->addField('code');
        $i->addField('net', ['type' => 'integer']);
        $i->addField('vat', ['type' => 'integer']);
        $i->addExpression('gross', ['expr' => '[net] + [vat]', 'type' => 'integer']);

        $i->setOrder('gross');
        $i->setOnlyFields(['gross']);
        self::assertSame([
            ['gross' => 14],
            ['gross' => 15],
            ['gross' => 19],
        ], $i->export());

        // order by expression not defined as separate expression field in model
        $i->order = []; // reset
        $i->setOrder($i->expr('[net] * [vat]'));
        $i->setOnlyFields(['code']);
        self::assertSame([
            ['code' => 'B'], // 10 * 4 = 40
            ['code' => 'A'], // 10 * 5 = 50
            ['code' => 'C'], // 15 * 4 = 60
        ], $i->export());

        // "desc" as part of expression string
        $i->order = []; // reset
        $i->setOrder($i->expr('[net] * [vat] desc'));
        $i->setOnlyFields(['code']);
        self::assertSame([
            ['code' => 'C'], // 15 * 4 = 60
            ['code' => 'A'], // 10 * 5 = 50
            ['code' => 'B'], // 10 * 4 = 40
        ], $i->export());

        // "desc" as 2nd parameter
        $i->order = []; // reset
        $i->setOrder($i->expr('[net] * [vat]'), 'desc');
        $i->setOnlyFields(['code']);
        self::assertSame([
            ['code' => 'C'], // 15 * 4 = 60
            ['code' => 'A'], // 10 * 5 = 50
            ['code' => 'B'], // 10 * 4 = 40
        ], $i->export());

        // order by mixed array of expressions and field names
        $i->order = []; // reset
        $i->setOrder(['vat', $i->expr('[net] * [vat]')]);
        $i->setOnlyFields(['code']);
        self::assertSame([
            ['code' => 'B'], // 4, 10 * 4 = 40
            ['code' => 'C'], // 4, 15 * 4 = 60
            ['code' => 'A'], // 5, 10 * 5 = 50
        ], $i->export());
    }

    public function testOrderByUnsupportedParamException(): void
    {
        $this->setDb([
            'invoice' => [
                ['net' => 10],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('net', ['type' => 'integer']);
        $i->setOrder(new \DateTime()); // @phpstan-ignore-line

        $this->expectException(\TypeError::class);
        $i->export();
    }

    public function testLimit(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice', 'idField' => false]);
        $i->addField('total_net', ['type' => 'integer']);
        $i->addField('total_vat', ['type' => 'integer']);
        $i->addExpression('total_gross', ['expr' => '[total_net] + [total_vat]', 'type' => 'integer']);

        $i->setOrder('total_net');
        $i->setOnlyFields(['total_net']);
        self::assertSame([
            ['total_net' => 10],
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());

        $ii = $i;
        $i = clone $ii;
        $i->setLimit(2);
        self::assertSame([
            ['total_net' => 10],
            ['total_net' => 15],
        ], $i->export());

        $i = clone $ii;
        $i->setLimit(2, 1);
        self::assertSame([
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());

        $i = clone $ii;
        $i->setLimit(null, 1);
        self::assertSame([
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());
    }

    public function testLimitBug1010(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('total_net', ['type' => 'integer']);
        $i->setOrder('total_net');

        self::assertSame(10, $i->loadAny()->get('total_net'));
        $i->setLimit(2);
        self::assertSame(10, $i->loadAny()->get('total_net'));

        $i->setLimit(2, 2);
        self::assertSame(20, $i->loadAny()->get('total_net'));
        self::assertSame(20, $i->loadOne()->get('total_net'));

        $i->setLimit(1);
        self::assertSame(10, $i->loadAny()->get('total_net'));
        self::assertSame(10, $i->loadOne()->get('total_net'));
        $i->setLimit(1, 1);
        self::assertSame(15, $i->loadAny()->get('total_net'));
        self::assertSame(15, $i->loadOne()->get('total_net'));
        $i->setLimit(1, 2);
        self::assertSame(20, $i->loadAny()->get('total_net'));
        self::assertSame(20, $i->loadOne()->get('total_net'));
        $i->setLimit(1, 3);
        self::assertNull($i->tryLoadAny());
        self::assertNull($i->tryLoadOne());

        $i->setLimit(0);
        self::assertNull($i->tryLoadAny());
        self::assertNull($i->tryLoadOne());
    }
}
