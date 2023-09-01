<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\TestCase;

class StAccount extends Model
{
    public $table = 'account';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');

        $this->hasMany('Transactions', ['model' => [StGenericTransaction::class]])
            ->addField('balance', ['aggregate' => 'sum', 'field' => 'amount']);

        $this->hasMany('Transactions:Deposit', ['model' => [StTransaction_Deposit::class]]);
        $this->hasMany('Transactions:Withdrawal', ['model' => [StTransaction_Withdrawal::class]]);
        $this->hasMany('Transactions:Ob', ['model' => [StTransaction_Ob::class]])
            ->addField('opening_balance', ['aggregate' => 'sum', 'field' => 'amount']);

        $this->hasMany('Transactions:TransferOut', ['model' => [StTransaction_TransferOut::class]]);
        $this->hasMany('Transactions:TransferIn', ['model' => [StTransaction_TransferIn::class]]);
    }

    /**
     * @return static
     */
    public static function open(Persistence $persistence, string $name, float $amount = 0.0)
    {
        $m = new static($persistence);
        $m = $m->createEntity();
        $m->save(['name' => $name]);

        if ($amount) {
            $m->ref('Transactions:Ob')->createEntity()->save(['amount' => $amount]);
        }

        return $m;
    }

    public function deposit(float $amount): Model
    {
        return $this->ref('Transactions:Deposit')->createEntity()->save(['amount' => $amount]);
    }

    public function withdraw(float $amount): Model
    {
        return $this->ref('Transactions:Withdrawal')->createEntity()->save(['amount' => $amount]);
    }

    /**
     * @return array<int, Model>
     */
    public function transferTo(self $account, float $amount): array
    {
        $out = $this->ref('Transactions:TransferOut')->createEntity()->save(['amount' => $amount]);
        $in = $account->ref('Transactions:TransferIn')->createEntity()->save(['amount' => $amount, 'link_id' => $out->getId()]);
        $out->set('link_id', $in->getId());
        $out->save();

        return [$in, $out];
    }
}

class StGenericTransaction extends Model
{
    public $table = 'transaction';
    /** @var string */
    public $type;

    protected function init(): void
    {
        parent::init();

        $this->hasOne('account_id', ['model' => [StAccount::class]]);
        $this->addField('type', ['enum' => ['Ob', 'Deposit', 'Withdrawal', 'TransferOut', 'TransferIn']]);

        if ($this->type) {
            $this->addCondition('type', $this->type);
        }
        $this->addField('amount', ['type' => 'atk4_money']);

        $this->onHookShort(Model::HOOK_AFTER_LOAD, function () {
            if (static::class !== $this->getClassName()) {
                $cl = $this->getClassName();
                $cl = new $cl($this->getModel()->getPersistence());
                $cl = $cl->load($this->getId());

                $this->breakHook($cl);
            }
        });
    }

    /**
     * @return class-string<self>
     */
    public function getClassName(): string
    {
        return __NAMESPACE__ . '\StTransaction_' . $this->get('type');
    }
}

class StTransaction_Ob extends StGenericTransaction
{
    public $type = 'Ob';
}

class StTransaction_Deposit extends StGenericTransaction
{
    public $type = 'Deposit';
}

class StTransaction_Withdrawal extends StGenericTransaction
{
    public $type = 'Withdrawal';
}

class StTransaction_TransferOut extends StGenericTransaction
{
    public $type = 'TransferOut';

    protected function init(): void
    {
        parent::init();

        $this->hasOne('link_id', ['model' => [StTransaction_TransferIn::class]]);

        // $this->join('transaction', 'linked_transaction');
    }
}

class StTransaction_TransferIn extends StGenericTransaction
{
    public $type = 'TransferIn';

    protected function init(): void
    {
        parent::init();

        $this->hasOne('link_id', ['model' => [StTransaction_TransferOut::class]]);
    }
}

class SubTypesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createMigrator(new StAccount($this->db))->create();
        $this->createMigrator(new StTransaction_TransferOut($this->db))->create();
    }

    public function testBasic(): void
    {
        $inheritance = StAccount::open($this->db, 'inheritance', 1000);
        $current = StAccount::open($this->db, 'current');

        $inheritance->transferTo($current, 500);
        $current->withdraw(350);

        self::assertInstanceOf(StTransaction_Ob::class, $inheritance->ref('Transactions')->load(1));
        self::assertInstanceOf(StTransaction_TransferOut::class, $inheritance->ref('Transactions')->load(2));
        self::assertInstanceOf(StTransaction_TransferIn::class, $current->ref('Transactions')->load(3));
        self::assertInstanceOf(StTransaction_Withdrawal::class, $current->ref('Transactions')->load(4));

        $assertClassesFx = static function (array $expectedClasses) use ($current): void {
            $classes = [];
            foreach ($current->ref('Transactions')->setOrder('id') as $tr) {
                $classes[] = get_class($tr);
            }
            self::assertSame($expectedClasses, $classes);
        };

        $assertClassesFx([
            StTransaction_TransferIn::class,
            StTransaction_Withdrawal::class,
        ]);

        $current->deposit(50);
        $current->deposit(50);
        self::assertInstanceOf(StTransaction_Deposit::class, $current->ref('Transactions')->load(5));
        self::assertInstanceOf(StTransaction_Deposit::class, $current->ref('Transactions')->load(5));

        $assertClassesFx([
            StTransaction_TransferIn::class,
            StTransaction_Withdrawal::class,
            StTransaction_Deposit::class,
            StTransaction_Deposit::class,
        ]);
    }
}
