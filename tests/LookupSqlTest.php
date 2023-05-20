<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

/**
 * You can lookup country by name or by code. We will also try looking up country by
 * multiple fields (e.g. code and is_eu) to see if those work are used as AND conditions. For instance
 * is_eu = true, code = 'US', should not be able to lookup the country.
 *
 * Users is a reference. You can specify it as an array containing import data for and that will be inserted
 * recursively.
 *
 * We also introduced user_names field, which will concatenate all user names for said country. It can also be
 * used when importing, simply provide a comma-separated string of user names and they will be CREATED for you.
 */
class LCountry extends Model
{
    public $table = 'country';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('code');

        $this->addField('is_eu', ['type' => 'boolean', 'default' => false]);

        $this->hasMany('Users', ['model' => [LUser::class]])
            ->addField('user_names', ['field' => 'name', 'concat' => ', ']);
    }
}

/**
 * User has one country and may have friends. Friend is a many-to-many relationship between users.
 *
 * When importing users, you should be able to specify country using 'country_id' or using some of the
 * lookup fields: 'country', 'country_code' or 'is_eu'.
 *
 * If ID is not specified (unlike specifying null!) we will rely on the lookup fields to try and find
 * a country. If multiple lookup fields are set, we should find a country that matches them all. If country
 * cannot be found then null should be set for country_id.
 *
 * Friends is many-to-many relationship. We have 'friend_names' field which may be similar to the one we had
 * for a country. However specifying friend_names as a comma-separated value will not create any friends.
 * Instead it will look up existing records and will create "Friend" record for all of them.
 *
 * Like before Friends can also be specified as an array.
 */
class LUser extends Model
{
    public $table = 'user';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('is_vip', ['type' => 'boolean', 'default' => false]);

        $this->hasOne('country_id', ['model' => [LCountry::class]])
            ->addFields(['country_code' => 'code', 'is_eu'])
            ->addTitle();

        $this->hasMany('Friends', ['model' => [LFriend::class]])
            ->addField('friend_names', ['field' => 'friend_name', 'concat' => '; ']);
    }
}

/**
 * Friend is a many-to-many binder that connects User with another User.
 *
 * In our case, however, we want each friendship to be reciprocal. If John
 * is a friend of Sue, then Sue must be a friend of John.
 *
 * To implement that, we include insert / delete handlers which would create
 * a reverse record.
 *
 * The challenge here is to make sure that those handlers are executed automatically
 * while importing User and Friends.
 */
class LFriend extends Model
{
    public $table = 'friend';
    public ?string $titleField = 'friend_name';

    protected bool $skipReverse = false;

    protected function init(): void
    {
        parent::init();

        $this->hasOne('user_id', ['model' => [LUser::class]])
            ->addField('my_name', 'name');
        $this->hasOne('friend_id', ['model' => [LUser::class]])
            ->addField('friend_name', 'name');

        // add/remove reverse friendships
        $this->onHookShort(self::HOOK_AFTER_INSERT, function () {
            if ($this->skipReverse) {
                return;
            }

            $c = $this->getModel()->createEntity();
            $c->skipReverse = true;
            // $c->insert([
            //     'user_id' => $this->get('friend_id'),
            //     'friend_id' => $this->get('user_id'),
            // ]);
        });

        $this->onHookShort(Model::HOOK_BEFORE_DELETE, function () {
            if ($this->skipReverse) {
                return;
            }

            // $c = $this->getModel()->loadBy([
            //     'user_id' => $this->get('friend_id'),
            //     'friend_id' => $this->get('user_id'),
            // ]);
            // $c->skipReverse = true;
            // $c->delete();
        });
    }
}

class LookupSqlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $country = new LCountry($this->db);
        $this->createMigrator($country)->create();
        $user = new LUser($this->db);
        $this->createMigrator($user)->create();
        $friend = new LFriend($this->db);
        $this->createMigrator($friend)->create();
        $this->createMigrator()->createForeignKey($user->getReference('country_id'));
        $this->createMigrator()->createForeignKey($friend->getReference('user_id'));
        $this->createMigrator()->createForeignKey($friend->getReference('friend_id'));
    }

    public function testImportCountriesBasic(): void
    {
        $c = new LCountry($this->db);

        $results = [];

        // should be OK, will set country name, rest of fields will be null
        $c->createEntity()->saveAndUnload(['name' => 'Canada']);

        // adds another country, but with more fields
        $c->createEntity()->saveAndUnload(['name' => 'Latvia', 'code' => 'LV', 'is_eu' => true]);

        // setting field prior will affect save()
        $cc = $c->createEntity();
        $cc->set('is_eu', true);
        $cc->save(['name' => 'Estonia', 'code' => 'ES']);

        // is_eu will NOT BLEED into this record, because insert() does not make use of current model values.
        $c->insert(['name' => 'Korea', 'code' => 'KR']);

        // is_eu will NOT BLEED into Japan or Russia, because import() treats all records individually
        $c->import([
            ['name' => 'Japan', 'code' => 'JP'],
            ['name' => 'Lithuania', 'code' => 'LT', 'is_eu' => true],
            ['name' => 'Russia', 'code' => 'RU'],
        ]);

        self::assertSameExportUnordered([
            'country' => [
                1 => [
                    'id' => 1,
                    'name' => 'Canada',
                    'code' => null,
                    'is_eu' => '0',
                ],
                [
                    'id' => 2,
                    'name' => 'Latvia',
                    'code' => 'LV',
                    'is_eu' => '1',
                ],
                [
                    'id' => 3,
                    'name' => 'Estonia',
                    'code' => 'ES',
                    'is_eu' => '1',
                ],
                [
                    'id' => 4,
                    'name' => 'Korea',
                    'code' => 'KR',
                    'is_eu' => '0',
                ],
                [
                    'id' => 5,
                    'name' => 'Japan',
                    'code' => 'JP',
                    'is_eu' => '0',
                ],
                [
                    'id' => 6,
                    'name' => 'Lithuania',
                    'code' => 'LT',
                    'is_eu' => '1',
                ],
                [
                    'id' => 7,
                    'name' => 'Russia',
                    'code' => 'RU',
                    'is_eu' => '0',
                ],
            ],
        ], $this->getDb(['country']));
    }

    public function testImportInternationalUsers(): void
    {
        $c = new LCountry($this->db);

        if ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::markTestIncomplete('TODO MSSQL: Cannot perform an aggregate function on an expression containing an aggregate or a subquery');
        }

        $c->insert(['name' => 'Canada', 'Users' => [['name' => 'Alain'], ['name' => 'Duncan', 'is_vip' => true]]]);
        $c->insert(['name' => 'Latvia', 'Users' => [['name' => 'imants'], ['name' => 'juris']]]);

        self::assertSameExportUnordered([
            'country' => [
                1 => [
                    'id' => 1,
                    'name' => 'Canada',
                    'code' => null,
                    'is_eu' => '0',
                ],
                [
                    'id' => 2,
                    'name' => 'Latvia',
                    'code' => null,
                    'is_eu' => '0',
                ],
            ],
            'user' => [
                1 => [
                    'id' => 1,
                    'name' => 'Alain',
                    'is_vip' => '0',
                    'country_id' => 1,
                ],
                [
                    'id' => 2,
                    'name' => 'Duncan',
                    'is_vip' => '1',
                    'country_id' => 1,
                ],
                [
                    'id' => 3,
                    'name' => 'imants',
                    'is_vip' => '0',
                    'country_id' => 2,
                ],
                [
                    'id' => 4,
                    'name' => 'juris',
                    'is_vip' => '0',
                    'country_id' => 2,
                ],
            ],
        ], $this->getDb(['country', 'user']));
    }

    public function testImportByLookup(): void
    {
        $c = new LCountry($this->db);

        $c->import([
            ['name' => 'Canada', 'code' => 'CA'],
            ['name' => 'Latvia', 'code' => 'LV', 'is_eu' => true],
            ['name' => 'Japan', 'code' => 'JP'],
            ['name' => 'Lithuania', 'code' => 'LT', 'is_eu' => true],
            ['name' => 'Russia', 'code' => 'RU'],
        ]);

        $u = new LUser($this->db);

        self::assertTrue($u->getField('country_id')->isEditable());
        self::assertFalse($u->getField('country')->isEditable());
        self::assertFalse($u->getField('country_code')->isEditable());

        $u->import([
            ['name' => 'Alain', 'country_code' => 'CA'],
            ['name' => 'Imants', 'country_code' => 'LV'],
            // ['name' => 'Romans', 'country_code' => 'UK'], // country code does not exist
        ]);

        self::assertSameExportUnordered([
            'country' => [
                1 => [
                    'id' => 1,
                    'name' => 'Canada',
                    'code' => 'CA',
                    'is_eu' => '0',
                ],
                [
                    'id' => 2,
                    'name' => 'Latvia',
                    'code' => 'LV',
                    'is_eu' => '1',
                ],
                [
                    'id' => 3,
                    'name' => 'Japan',
                    'code' => 'JP',
                    'is_eu' => '0',
                ],
                [
                    'id' => 4,
                    'name' => 'Lithuania',
                    'code' => 'LT',
                    'is_eu' => '1',
                ],
                [
                    'id' => 5,
                    'name' => 'Russia',
                    'code' => 'RU',
                    'is_eu' => '0',
                ],
            ],
            'user' => [
                1 => [
                    'id' => 1,
                    'name' => 'Alain',
                    'is_vip' => '0',
                    'country_id' => 1,
                ],
                [
                    'id' => 2,
                    'name' => 'Imants',
                    'is_vip' => '0',
                    'country_id' => 2,
                ],
            ],
        ], $this->getDb(['country', 'user']));
    }

    public function testImportInternationalFriends(): void
    {
        $c = new LCountry($this->db);

        if ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::markTestIncomplete('TODO MSSQL: Cannot perform an aggregate function on an expression containing an aggregate or a subquery');
        }

        $c->insert(['name' => 'Canada', 'Users' => [['name' => 'Alain'], ['name' => 'Duncan', 'is_vip' => true]]]);
        $c->insert(['name' => 'Latvia', 'Users' => [['name' => 'imants'], ['name' => 'juris']]]);

        self::assertSame('imants, juris', $c->loadBy('name', 'Latvia')->get('user_names'));

        $user1 = $c->ref('Users')->loadBy('name', 'Duncan');
        $user2 = $c->loadBy('name', 'Latvia')->ref('Users')->loadBy('name', 'imants');
        $user3 = $user2->getModel()->loadBy('name', 'juris');

        $user2->ref('Friends')->import([
            ['friend_id' => $user1->getId()],
            ['friend_id' => $user3->getId()],
        ]);

        self::assertNull($user1->get('friend_names'));
        self::assertNull($user2->get('friend_names'));
        self::assertNull($user3->get('friend_names'));
        $user1->reload();
        $user2->reload();
        $user3->reload();
        self::assertNull($user1->get('friend_names'));
        self::assertSame('Duncan; juris', $user2->get('friend_names'));
        self::assertNull($user3->get('friend_names'));

        /* TODO - that's left for hasMTM implementation..., to be coming later
        // Specifying hasMany here will perform input
        $c->insert(['Canada', 'Users' => ['Alain', ['Duncan', 'is_vip' => true]]]);

        // Inserting Users into Latvia can also specify Friends. In this case Friend name will be looked up
        $c->insert(['Latvia', 'Users' => ['Imants', ['Juris', 'friend_names' => 'Alain, Imants' TODO convert to array, we never split implicitly ]]]);

        // Inserting This time explicitly specify friend attributes
        $c->insert(['UK', 'Users' => [
            ['Romans', 'Friends' => [
                ['friend_id' => 1],
                ['friend_name' => 'Juris'],
                'Alain',
            ]],
        ]]);

        // BTW - Alain should have 3 friends here
        */
    }
}
