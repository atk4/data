<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Schema\TestCase;
use Atk4\Data\Tests\Model\Smbo\Account;
use Atk4\Data\Tests\Model\Smbo\Payment;
use Atk4\Data\Tests\Model\Smbo\Transfer;

class SmboTransferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createMigrator()->table('account')
            ->id()
            ->field('name')
            ->create();

        $this->createMigrator()->table('document')
            ->id()
            ->field('reference')
            ->field('doc_type')
            ->field('amount', ['type' => 'float'])
            ->create();

        $this->createMigrator()->table('payment')
            ->id()
            ->field('document_id', ['type' => 'integer'])
            ->field('account_id', ['type' => 'integer'])
            ->field('cheque_no')
            ->field('misc_payment', ['type' => 'boolean'])
            ->field('transfer_document_id', ['type' => 'integer'])
            ->create();
    }

    public function testTransferBetweenAccounts(): void
    {
        $aib = (new Account($this->db))->createEntity()->save(['name' => 'AIB']);
        $boi = (new Account($this->db))->createEntity()->save(['name' => 'BOI']);

        $t = $aib->transfer($boi, 100); // create transfer between accounts
        $t->save();

        self::assertSame(-100.0, $aib->reload()->get('balance'));
        self::assertSame(100.0, $boi->reload()->get('balance'));

        $t = new Transfer($this->db);
        $data = $t->export(['id', 'transfer_document_id']);
        usort($data, static function ($e1, $e2) {
            return $e1['id'] < $e2['id'] ? -1 : 1;
        });
        self::assertSame([
            ['id' => 1, 'transfer_document_id' => 2],
            ['id' => 2, 'transfer_document_id' => 1],
        ], $data);
    }

    public function testRef(): void
    {
        // create accounts and payments
        $a = new Account($this->db);

        $aa = $a->createEntity();
        $aa->save(['name' => 'AIB']);
        $aa->ref('Payment')->createEntity()->save(['amount' => 10]);
        $aa->ref('Payment')->createEntity()->save(['amount' => 20]);

        $aa = $a->createEntity();
        $aa->save(['name' => 'BOI']);
        $aa->ref('Payment')->createEntity()->save(['amount' => 30]);

        // create payment without link to account
        $p = new Payment($this->db);
        $p->createEntity()->saveAndUnload(['amount' => 40]);

        // Account is not loaded, will dump all Payments related to ANY Account
        $data = $a->ref('Payment')->export(['amount']);
        self::assertSameExportUnordered([
            ['amount' => 10.0],
            ['amount' => 20.0],
            ['amount' => 30.0],
            // ['amount' => 40.0], // will not select this because it is not related to any Account
        ], $data);

        // Account is loaded, will dump all Payments related to that particular Account
        $a = $a->load(1);
        $data = $a->ref('Payment')->export(['amount']);
        self::assertSameExportUnordered([
            ['amount' => 10.0],
            ['amount' => 20.0],
        ], $data);
    }
}
