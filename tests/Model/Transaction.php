<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

use Atk4\Data\Model\UnionModel;

class Transaction extends UnionModel
{
    /** @var Invoice */
    public $nestedInvoice;
    /** @var Payment */
    public $nestedPayment;

    /** @var bool */
    public $subtractInvoice;

    #[\Override]
    protected function init(): void
    {
        parent::init();

        // first lets define nested models
        $this->nestedInvoice = new Invoice();
        $this->addNestedModel($this->nestedInvoice, $this->subtractInvoice ? ['amount' => '-[]'] : []);
        $this->nestedPayment = new Payment();
        $this->addNestedModel($this->nestedPayment);

        // next, define common fields
        $this->hasOne('client_id', ['model' => [Client::class]]);
        $this->addField('name');
        $this->addField('amount', ['type' => 'atk4_money']);
    }
}
