<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

use Atk4\Data\Model;

class Payment extends Model
{
    public $table = 'payment';

    protected function init(): void
    {
        parent::init();
        $this->addField('name');

        $this->hasOne('client_id', ['model' => [Client::class]]);
        $this->addField('amount', ['type' => 'money']);
    }
}
