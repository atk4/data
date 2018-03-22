<?php

namespace atk4\data\tests\smbo;

class SMBOTestCase extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function setUp()
    {
        parent::setUp();

        $s = $this->getMigration();

        $x = clone $s;
        $x->table('account')->drop()
            ->id()
            ->field('name')
            ->create();

        $x = clone $s;
        $x->table('document')->drop()
            ->id()
            ->field('reference')
            ->field('contact_from_id')
            ->field('contact_to_id')
            ->field('doc_type')
            ->field('amount', ['type' => 'decimal(8,2)'])
            ->create();

        $x = clone $s;
        $x->table('payment')->drop()
            ->id()
            ->field('document_id', ['type' => 'integer'])
            ->field('account_id', ['type' => 'integer'])
            ->field('cheque_no')
            //->field('misc_payment', ['type' => 'enum(\'N\',\'Y\')'])
            ->field('misc_payment', ['type' => 'varchar(2)'])
            ->field('transfer_document_id')
            ->create();
    }
}
