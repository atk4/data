<?php

declare(strict_types=1);

namespace atk4\data\tests\Model;

use atk4\data\Model;

class Invoice extends Model
{
    public $table = 'invoice';

    public function init(): void
    {
        parent::init();
        $this->addField('name');

        $this->hasOne('client_id', [Client::class]);
        $this->addField('amount', ['type' => 'money']);
    }
}
