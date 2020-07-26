<?php

declare(strict_types=1);

namespace atk4\data\tests\Model;

use atk4\data\Model\Union;

class Transaction2 extends Union
{
    public $nestedInvoice;
    public $nestedPayment;
    
    public function init(): void
    {
        parent::init();

        // first lets define nested models
        $this->nestedInvoice = $this->addNestedModel(new Invoice(), ['amount' => '-[]']);
        $this->nestedPayment = $this->addNestedModel(new Payment());

        //$this->nestedInvoice->hasOne('client_id', [new Client()]);
        //$this->nestedPayment->hasOne('client_id', [new Client()]);

        // next, define common fields
        $this->addField('name');
        $this->addField('amount', ['type' => 'money']);
        //$this->hasOne('client_id', [new Client()]);
    }
}
