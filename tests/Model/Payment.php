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

        $this->hasOne('client_id', ['model' => [Client::class]]);
        $this->addField('name');
        $this->addField('amount', ['type' => 'atk4_money']);
    }
}
