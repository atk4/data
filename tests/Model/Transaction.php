<?php

declare(strict_types=1);

namespace atk4\data\tests\Model;

use atk4\data\Model\Union;

class Transaction extends Union
{
    public $nestedInvoice;
    public $nestedPayment;

    public $subtractInvoice;

    public function init(): void
    {
        parent::init();

        // first lets define nested models
        $this->nestedInvoice = $this->addNestedModel(new Invoice(), $this->subtractInvoice ? ['amount' => '-[]'] : []);
        $this->nestedPayment = $this->addNestedModel(new Payment());

        // next, define common fields
        $this->addField('name');
        $this->addField('amount', ['type' => 'money']);
    }
}
