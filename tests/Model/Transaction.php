<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

use Atk4\Data\Model\Union;

class Transaction extends Union
{
    public $nestedInvoice;
    public $nestedPayment;

    public $subtractInvoice;

    protected function init(): void
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
