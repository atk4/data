<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

use Atk4\Data\Model;

class Invoice extends Model
{
    public $table = 'invoice';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');

        $this->hasOne('client_id', [Client::class]);
        $this->addField('amount', ['type' => 'money']);
    }
}
