<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference;
use Atk4\Data\Schema\TestCase;
use Atk4\Data\Tests\ContainsMany\Invoice;
use Atk4\Data\Tests\ContainsMany\Line;
use Atk4\Data\Tests\ContainsMany\VatRate;

/**
 * Model structure:.
 *
 * Invoice (SQL)
 *   - containsMany(Line)
 *     - hasOne(VatRate, SQL)
 *     - containsMany(Discount)
 */
class ContainsManyTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->createMigrator(new VatRate($this->db))->create();
        $this->createMigrator(new Invoice($this->db))->create();

        $m = new VatRate($this->db);
        $m->import([
            [
                $m->fieldName()->id => 1,
                $m->fieldName()->name => '21% rate',
                $m->fieldName()->rate => 21,
            ],
            [
                $m->fieldName()->id => 2,
                $m->fieldName()->name => '15% rate',
                $m->fieldName()->rate => 15,
            ],
        ]);

        $m = new Invoice($this->db);
        $m->import([
            [
                $m->fieldName()->id => 1,
                $m->fieldName()->ref_no => 'A1',
                $m->fieldName()->amount => 123,
            ],
            [
                $m->fieldName()->id => 2,
                $m->fieldName()->ref_no => 'A2',
                $m->fieldName()->amount => 456,
            ],
        ]);
    }

    public function testModelCaption(): void
    {
        $i = new Invoice($this->db);

        // test caption of containsMany reference
        self::assertSame('My Invoice Lines', $i->getField($i->fieldName()->lines)->getCaption());
        self::assertSame('My Invoice Lines', $i->getReference($i->fieldName()->lines)->createTheirModel()->getModelCaption());
        self::assertSame('My Invoice Lines', $i->lines->getModelCaption());
    }

    public function testContainsMany(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        self::assertSame(Line::class, get_class($i->getModel()->lines));

        // now let's add some lines
        $l = $i->lines;
        $rows = [
            [
                $l->fieldName()->id => 1,
                $l->fieldName()->vat_rate_id => 1,
                $l->fieldName()->price => 10,
                $l->fieldName()->qty => 2,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
            [
                $l->fieldName()->id => 2,
                $l->fieldName()->vat_rate_id => 2,
                $l->fieldName()->price => 15,
                $l->fieldName()->qty => 5,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
            [
                $l->fieldName()->id => 3,
                $l->fieldName()->vat_rate_id => 1,
                $l->fieldName()->price => 40,
                $l->fieldName()->qty => 1,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
        ];

        foreach ($rows as $row) {
            unset($row[$l->fieldName()->discounts]);

            $l->insert($row);
        }

        // reload invoice just in case
        self::assertSameExportUnordered($rows, $i->lines->export());
        $i->reload();
        self::assertSameExportUnordered($rows, $i->lines->export());
        $i = $i->getModel()->load($i->getId());
        self::assertSameExportUnordered($rows, $i->lines->export());

        // now let's delete line with id=2 and add one more line
        $i->lines
            ->load(2)->delete()->getModel()
            ->insert([
                $l->fieldName()->vat_rate_id => 2,
                $l->fieldName()->price => 50,
                $l->fieldName()->qty => 3,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ]);
        $rows = [
            [
                $l->fieldName()->id => 1,
                $l->fieldName()->vat_rate_id => 1,
                $l->fieldName()->price => 10,
                $l->fieldName()->qty => 2,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
            [
                $l->fieldName()->id => 3,
                $l->fieldName()->vat_rate_id => 1,
                $l->fieldName()->price => 40,
                $l->fieldName()->qty => 1,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
            [
                $l->fieldName()->id => 4,
                $l->fieldName()->vat_rate_id => 2,
                $l->fieldName()->price => 50,
                $l->fieldName()->qty => 3,
                $l->fieldName()->discounts => null,
                $l->fieldName()->add_date => new \DateTime('2019-01-01'),
            ],
        ];
        self::assertSameExportUnordered($rows, $i->lines->export());

        // try hasOne reference
        $v = $i->lines->load(4)->vat_rate_id;
        self::assertSame(15, $v->rate);

        // test expression fields
        $v = $i->lines->load(4);
        self::assertSame(50 * 3 * 1.15, $v->total_gross);

        // and what about calculated field?
        $i->reload(); // we need to reload invoice for changes in lines to be recalculated
        self::assertSame(10 * 2 * 1.21 + 40 * 1 * 1.21 + 50 * 3 * 1.15, $i->total_gross); // = 245.1
    }

    public function testNestedContainsMany(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        // now let's add some lines
        $l = $i->lines;

        $l->insert([
            $l->fieldName()->id => 1,
            $l->fieldName()->vat_rate_id => 1,
            $l->fieldName()->price => 10,
            $l->fieldName()->qty => 2,
            $l->fieldName()->add_date => new \DateTime('2019-06-01'),
        ]);
        $l->insert([
            $l->fieldName()->id => 2,
            $l->fieldName()->vat_rate_id => 2,
            $l->fieldName()->price => 15,
            $l->fieldName()->qty => 5,
            $l->fieldName()->add_date => new \DateTime('2019-07-01'),
        ]);

        // add some discounts
        $l->load(1)->discounts->insert([
            $l->discounts->fieldName()->id => 1,
            $l->discounts->fieldName()->percent => 5,
            $l->discounts->fieldName()->valid_till => new \DateTime('2019-07-15'),
        ]);
        $l->load(1)->discounts->insert([
            $l->discounts->fieldName()->id => 2,
            $l->discounts->fieldName()->percent => 10,
            $l->discounts->fieldName()->valid_till => new \DateTime('2019-07-30'),
        ]);
        $l->load(2)->discounts->insert([
            $l->discounts->fieldName()->id => 1,
            $l->discounts->fieldName()->percent => 20,
            $l->discounts->fieldName()->valid_till => new \DateTime('2019-12-31'),
        ]);

        // reload invoice to be sure all is saved and to recalculate all fields
        $i->reload();

        // ok, so now let's test
        self::assertSameExportUnordered([
            [
                $l->discounts->fieldName()->id => 1,
                $l->discounts->fieldName()->percent => 5,
                $l->discounts->fieldName()->valid_till => new \DateTime('2019-07-15'),
            ],
            [
                $l->discounts->fieldName()->id => 2,
                $l->discounts->fieldName()->percent => 10,
                $l->discounts->fieldName()->valid_till => new \DateTime('2019-07-30'),
            ],
        ], $i->lines->load(1)->discounts->export());

        // is total_gross correctly calculated?
        self::assertSame(10 * 2 * 1.21 + 15 * 5 * 1.15, $i->total_gross); // =110.45

        // do we also correctly calculate discounts from nested containsMany?
        self::assertSame(24.2 * 0.15 + 86.25 * 0.2, $i->discounts_total_sum); // =20.88

        // let's test how it all looks in persistence without typecasting
        $exportLines = $i->getModel()->setOrder($i->fieldName()->id)
            ->export(null, null, false)[0][$i->fieldName()->lines];
        $formatDtForCompareFx = static function (\DateTimeInterface $dt): string {
            $dt = (clone $dt)->setTimeZone(new \DateTimeZone('UTC')); // @phpstan-ignore method.notFound

            return $dt->format('Y-m-d H:i:s.u');
        };
        self::assertJsonStringEqualsJsonString(
            json_encode([
                [
                    $i->lines->fieldName()->id => 1,
                    $i->lines->fieldName()->vat_rate_id => 1,
                    $i->lines->fieldName()->price => 10.0,
                    $i->lines->fieldName()->qty => 2,
                    $i->lines->fieldName()->add_date => $formatDtForCompareFx(new \DateTime('2019-06-01')),
                    $i->lines->fieldName()->discounts => json_encode([
                        [
                            $i->lines->discounts->fieldName()->id => 1,
                            $i->lines->discounts->fieldName()->percent => 5,
                            $i->lines->discounts->fieldName()->valid_till => $formatDtForCompareFx(new \DateTime('2019-07-15')),
                        ],
                        [
                            $i->lines->discounts->fieldName()->id => 2,
                            $i->lines->discounts->fieldName()->percent => 10,
                            $i->lines->discounts->fieldName()->valid_till => $formatDtForCompareFx(new \DateTime('2019-07-30')),
                        ],
                    ]),
                ],
                [
                    $i->lines->fieldName()->id => 2,
                    $i->lines->fieldName()->vat_rate_id => 2,
                    $i->lines->fieldName()->price => 15.0,
                    $i->lines->fieldName()->qty => 5,
                    $i->lines->fieldName()->add_date => $formatDtForCompareFx(new \DateTime('2019-07-01')),
                    $i->lines->fieldName()->discounts => json_encode([
                        [
                            $i->lines->discounts->fieldName()->id => 1,
                            $i->lines->discounts->fieldName()->percent => 20,
                            $i->lines->discounts->fieldName()->valid_till => $formatDtForCompareFx(new \DateTime('2019-12-31')),
                        ],
                    ]),
                ],
            ]),
            $exportLines
        );
    }

    public function testCreateTheirModelContainedInPersistence(): void
    {
        $i = new Invoice($this->db);
        self::assertNotSame($i->getPersistence(), $i->lines->getPersistence());
        self::assertSame($i->getPersistence(), $i->lines->getField($i->lines->fieldName()->vat_rate_id)->getReference()->createTheirModel()->getPersistence());
    }

    public function testRefContainedInPersistence(): void
    {
        $i = new Invoice($this->db);
        self::assertNotSame($i->getPersistence(), $i->lines->getPersistence());
        self::assertSame($i->getPersistence(), $i->lines->vat_rate_id->getPersistence());
        self::assertSame($i->getPersistence(), $i->lines->discounts->containedInPersistence);
    }

    /**
     * @param mixed $log
     *
     * @param-out list<array{class-string<Model>, Model::HOOK_*}> $log
     */
    private function createInvoiceEntityWithLogger(&$log): Invoice
    {
        $log = [];
        $addLogHooksFx = static function (Model $m) use (&$log) {
            foreach ([Model::HOOK_BEFORE_SAVE, Model::HOOK_AFTER_SAVE, Model::HOOK_BEFORE_DELETE, Model::HOOK_AFTER_DELETE] as $spot) {
                foreach ([\PHP_INT_MIN, \PHP_INT_MAX] as $priority) {
                    $m->onHook($spot, static function (Model $m) use (&$log, $spot, $priority) {
                        $log[] = [get_class($m), $spot, $priority === \PHP_INT_MIN ? '>' : '<'];
                    }, [], $priority);
                }
            }
        };

        $invoice = new Invoice($this->db);
        $addLogHooksFx($invoice);

        $createTheirModelFx = static function () use ($addLogHooksFx) {
            $line = new Line();
            $addLogHooksFx($line);

            return $line;
        };
        \Closure::bind(static fn () => $invoice->getField($invoice->fieldName()->lines)->getReference()->model = $createTheirModelFx, null, Reference::class)();

        $invoiceEntity = $invoice->loadBy($invoice->fieldName()->ref_no, 'A1');

        $invoice->getField($invoice->fieldName()->lines)
            ->getReference()->ref($invoiceEntity)
            ->import([
                [$invoice->lines->fieldName()->vat_rate_id => $invoice->lines->vat_rate_id->load(1)->id, $invoice->lines->fieldName()->price => 5, $invoice->lines->fieldName()->qty => 10],
                [$invoice->lines->fieldName()->vat_rate_id => $invoice->lines->vat_rate_id->load(1)->id, $invoice->lines->fieldName()->price => 6, $invoice->lines->fieldName()->qty => 20],
            ]);
        self::assertSame([
            [$invoice->lines->fieldName()->id => 1, $invoice->lines->fieldName()->price => 5.0],
            [$invoice->lines->fieldName()->id => 2, $invoice->lines->fieldName()->price => 6.0],
        ], $invoiceEntity->lines->export([$invoice->lines->fieldName()->id, $invoice->lines->fieldName()->price]));

        return $invoiceEntity;
    }

    public function testSaveHooks(): void
    {
        $i = $this->createInvoiceEntityWithLogger($log);

        self::assertSame([
            [Line::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Line::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Line::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '<'],
            [Line::class, Model::HOOK_AFTER_SAVE, '<'],
            [Line::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Line::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Line::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '<'],
            [Line::class, Model::HOOK_AFTER_SAVE, '<'],
        ], $log);

        $log = [];
        $line = $i->lines->loadBy($i->lines->fieldName()->price, 5);
        $line->price = 8;
        self::assertSame([], $log);
        $line->save();

        self::assertSame([
            [Line::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Line::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Line::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '<'],
            [Line::class, Model::HOOK_AFTER_SAVE, '<'],
        ], $log);

        $log = [];
        $line->save();
        self::assertSame([
            [Line::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Line::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Line::class, Model::HOOK_AFTER_SAVE, '>'],
            [Line::class, Model::HOOK_AFTER_SAVE, '<'],
        ], $log);
    }

    public function testDeleteHooksOnOwnerDelete(): void
    {
        $i = $this->createInvoiceEntityWithLogger($log);

        $log = [];
        $i->delete();

        self::assertSame([
            [Invoice::class, Model::HOOK_BEFORE_DELETE, '>'],
            [Line::class, Model::HOOK_BEFORE_DELETE, '>'],
            [Line::class, Model::HOOK_BEFORE_DELETE, '<'],
            [Line::class, Model::HOOK_AFTER_DELETE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '<'],
            [Line::class, Model::HOOK_AFTER_DELETE, '<'],
            [Line::class, Model::HOOK_BEFORE_DELETE, '>'],
            [Line::class, Model::HOOK_BEFORE_DELETE, '<'],
            [Line::class, Model::HOOK_AFTER_DELETE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '<'],
            [Line::class, Model::HOOK_AFTER_DELETE, '<'],
            [Invoice::class, Model::HOOK_BEFORE_DELETE, '<'],
            [Invoice::class, Model::HOOK_AFTER_DELETE, '>'],
            [Invoice::class, Model::HOOK_AFTER_DELETE, '<'],
        ], $log);
    }

    public function testDeleteHooksOnContainedDelete(): void
    {
        $i = $this->createInvoiceEntityWithLogger($log);

        $log = [];
        $i->lines->loadBy($i->lines->fieldName()->price, 5)->delete();

        $expectedLog = [
            [Line::class, Model::HOOK_BEFORE_DELETE, '>'],
            [Line::class, Model::HOOK_BEFORE_DELETE, '<'],
            [Line::class, Model::HOOK_AFTER_DELETE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '<'],
            [Line::class, Model::HOOK_AFTER_DELETE, '<'],
        ];
        self::assertSame($expectedLog, $log);

        $log = [];
        $i->lines->loadOne()->delete();

        self::assertSame($expectedLog, $log);
    }

    public function testDirtyException(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        $i->getDirtyRef()[$i->fieldName()->lines] = [];

        $theirEntity = $i->getField($i->fieldName()->lines)->getReference()->ref($i)->createEntity();

        $newData = [
            $i->lines->fieldName()->vat_rate_id => $i->lines->vat_rate_id->load(1)->id,
            $i->lines->fieldName()->price => 5,
            $i->lines->fieldName()->qty => 10,
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Field is required to be not dirty');
        $theirEntity->save($newData);
    }

    public function testUnmanagedDataModificationException(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        $i->getField($i->fieldName()->lines)->normalize([]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Contained model data cannot be modified directly');
        $i->set($i->fieldName()->lines, [0]);
    }
}
