<?php

namespace atk4\data\tests\smbo;

class SMBOTestCase extends \atk4\data\tests\SQLTestCase
{
    public function setUp()
    {
        parent::setUp();

        $s = new \atk4\data\tests\Structure(['connection' => $this->db->connection]);

        (clone $s)->table('account')->drop()
            ->id()
            ->field('name')
            ->create();


        (clone $s)->table('document')->drop()
            ->id()
            ->field('reference')
            ->field('contact_from_id')
            ->field('contact_to_id')
            ->field('doc_type')
            ->field('amount', ['type' => 'decimal(8,2)'])
            ->create();

        (clone $s)->table('payment')->drop()
            ->id()
            ->field('document_id', ['type' => 'int'])
            ->field('account_id', ['type' => 'int'])
            ->field('cheque_no')
            ->field('misc_payment', ['type' => 'enum("Y","N")'])
            ->field('transfer_document_id')
            ->create();
    }
}
