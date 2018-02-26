<?php

namespace atk4\data\tests\smbo;

use atk4\data\Persistence;

class TransferTest extends SMBOTestCase
{
    public $debug = false;

    /**
     * Testing transfer between two accounts.
     */
    public function testTransfer()
    {
        $aib = (new Account($this->db))->save('AIB');
        $boi = (new Account($this->db))->save('BOI');

        $t = $aib->transfer($boi, 100); // create transfer between accounts

        $t->save();

        $this->assertEquals(-100, $aib->reload()['balance']);
        $this->assertEquals(100, $boi->reload()['balance']);

        $data = $t->export(['id', 'transfer_document_id']);
        usort($data, function ($e1, $e2) {
            return $e1['id'] < $e2['id'] ? -1 : 1;
        });
        $this->assertEquals([
            ['id' => '1', 'transfer_document_id' => '2'],
            ['id' => '2', 'transfer_document_id' => '1'],
        ], $data);
    }

    /*
    public function testBasicEntities()
    {
        $db = Persistence::connect($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);

        // Create a new company
        $company = new Model_Company($db);
        $company->set([
            'name'           => 'Test Company 1',
            'director_name'  => 'Tester Little',
            'type'           => 'Limited Company',
            'vat_registered' => true,
        ]);
        $company->save();

        return;

        // Create two new clients, one is sole trader, other is limited company
        $client = $company->ref('Client');
        list($john_id, $agile_id) = $m->insert([
            ['name' => 'John Smith Consulting', 'vat_registered' => false],
            'Agile Software Limited',
        ]);

        // Insert a first, default invoice for our sole-trader
        $john = $company->load($john_id);
        $john_invoices = $john->ref('Invoice');
        $john_invoices->insertInvoice([
            'ref_no'   => 'INV1',
            'due_date' => (new Date())->add(new DateInterval('2w')), // due in 2 weeks
            'lines'    => [
                ['descr' => 'Sold some sweets', 'total_gross' => 100.00],
                ['descr' => 'Delivery', 'total_gross' => 10.00],
            ],
        ]);

        // Use custom method to create a sub-nominal
        $company->ref('Nominal')->insertSubNominal('Sales', 'Discounted');

        // Insert our second invoice using set referencing
        $company->ref('Client')->id($agile_id)->refSet('Invoice')->insertInvoice([
            'lines' => [
                [
                    'item_id'   => $john->ref('Product')->insert('Cat Food'),
                    'nominal'   => 'Sales:Discounted',
                    'total_net' => 50.00,
                    'vat_rate'  => 23,
                    // calculates total_gross at 61.50.
                ],
                [
                    'item_id'   => $john->ref('Service')->insert('Delivery'),
                    'total_net' => 10.00,
                    'vat_rate'  => '23%',
                    // calculates total_gross at 12.30
                ],
            ],
        ]);

        // Next we create bank account
        $hsbc = $john->ref('Account')->set('name', 'HSBC')->save();

        // And each of our invoices will have one new payment
        foreach ($john_invoices as $invoice) {
            $invoice->ref('Payment')->insert(['amount' => 10.20, 'bank_account_id' => $hsbc]);
        }

        // Now let's execute report
        $debt = $john->add('Model_Report_Debtors');

        // This should give us total amount owed by all clients:
        // (100.00+10.00) + (61.50 + 12.30) - 10.20*2
        $this->assertEquals(163.40, $debt->sum('amount'));
    }
     */
}
