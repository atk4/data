<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

use Atk4\Data\Model2;

class Invoice extends Model2
{
    public $table = 'invoice';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->hasOne('client_id', ['model' => [Client::class]]);
        $this->addField('name');
        $this->addField('amount', ['type' => 'atk4_money']);
    }
}
