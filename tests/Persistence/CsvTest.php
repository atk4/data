<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Tests\Model\Person;

class CsvTest extends TestCase
{
    /** @var resource */
    protected $file;
    /** @var resource */
    protected $file2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = fopen('php://memory', 'w+');
        $this->file2 = fopen('php://memory', 'w+');
    }

    protected function tearDown(): void
    {
        fclose($this->file);
        $this->file = null; // @phpstan-ignore-line
        fclose($this->file2);
        $this->file2 = null; // @phpstan-ignore-line

        parent::tearDown();
    }

    /**
     * @param resource             $fileHandle
     * @param array<string, mixed> $defaults
     */
    protected function makeCsvPersistence($fileHandle, array $defaults = []): Persistence\Csv
    {
        return new class($fileHandle, $defaults) extends Persistence\Csv {
            /** @var resource */
            private $handleUnloaded;

            /**
             * @param resource             $fileHandle
             * @param array<string, mixed> $defaults
             */
            public function __construct($fileHandle, array $defaults)
            {
                parent::__construct('', $defaults);

                $this->handleUnloaded = $fileHandle;
            }

            public function openFile(string $mode = 'r'): void
            {
                $this->handle = $this->handleUnloaded;
                fseek($this->handle, 0);
            }

            public function closeFile(): void
            {
                if ($this->handle && get_resource_type($this->handle) === 'stream') {
                    $this->handle = null;
                    $this->header = null;
                }
            }
        };
    }

    /**
     * @param array<int, array<string, string>> $data
     */
    protected function setDb(array $data): void
    {
        ftruncate($this->file, 0);
        fputcsv($this->file, array_keys(reset($data)));
        foreach ($data as $row) {
            fputcsv($this->file, $row);
        }

        ftruncate($this->file2, 0);
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function getDb(): array
    {
        fseek($this->file, 0);
        $keys = fgetcsv($this->file);
        $data = [];
        while ($row = fgetcsv($this->file)) {
            $data[] = array_combine($keys, $row);
        }

        return $data;
    }

    public function testTestcase(): void
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);
        $data2 = $this->getDb();
        self::assertSame($data, $data2);
    }

    public function testLoadAny(): void
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);

        $p = $this->makeCsvPersistence($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m = $m->loadAny();

        self::assertSame('John', $m->get('name'));
        self::assertSame('Smith', $m->get('surname'));
    }

    public function testLoadAnyException(): void
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);

        $p = $this->makeCsvPersistence($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        $mm = $m->loadAny();
        $mm = $m->loadAny();

        self::assertSame('Sarah', $mm->get('name'));
        self::assertSame('Jones', $mm->get('surname'));

        $mm = $m->tryLoadAny();
        self::assertNull($mm);
    }

    public function testLoadByIdNotSupportedException(): void
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $this->setDb($data);

        $p = $this->makeCsvPersistence($this->file);
        $m = new Model($p);

        $this->expectException(Exception::class);
        $m->tryLoad(1);
    }

    public function testPersistenceCopy(): void
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
            ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
        ];

        $this->setDb($data);

        $p = $this->makeCsvPersistence($this->file);
        $p2 = $this->makeCsvPersistence($this->file2);

        $m = new Person($p);

        $m2 = $m->withPersistence($p2);

        // TODO should be not needed after https://github.com/atk4/data/pull/690 is merged
        // Exception: Csv persistence does not support other than LOAD ANY mode
        $m2->reloadAfterSave = false;

        foreach ($m as $row) {
            $m2->createEntity()->save($row->get());
        }

        fseek($this->file, 0);
        fseek($this->file2, 0);
        self::assertSame(
            stream_get_contents($this->file),
            stream_get_contents($this->file2)
        );
    }

    public function testExport(): void
    {
        $data = [
            ['name' => 'John', 'surname' => 'Smith'],
            ['name' => 'Sarah', 'surname' => 'Jones'],
        ];
        $this->setDb($data);

        $p = $this->makeCsvPersistence($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');

        self::assertSame([
            ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        self::assertSame([
            ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }
}
