<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

/**
 * Implements persistence driver that can save data and load from CSV file.
 * This basic driver only offers the load/save. It does not offer conditions or
 * id-specific operations. You can only use a single persistence object with
 * a single file.
 *
 * $p = new Persistence\Csv('file.csv');
 * $m = new MyModel($p);
 * $data = $m->export();
 *
 * Alternatively you can write into a file. First operation you perform on
 * the persistence will determine the mode.
 *
 * $p = new Persistence\Csv('file.csv');
 * $m = new MyModel($p);
 * $m->import($data);
 */
class Csv extends Persistence
{
    /** @var string Name of the file. */
    public $file;

    /** @var int Line in CSV file. */
    public $line = 0;

    /** @var resource|null File handle, when the file is opened. */
    public $handle;

    /**
     * Mode of operation. 'r' for reading and 'w' for writing.
     * If you manually set this operation, it will be used for file opening.
     *
     * @var string
     */
    public $mode;

    /** @var string Delimiter in CSV file. */
    public $delimiter = ',';
    /** @var string Enclosure in CSV file. */
    public $enclosure = '"';
    /** @var string Escape character in CSV file. */
    public $escapeChar = '\\';

    /** @var array<int, string>|null Array of field names. */
    public ?array $header = null;

    /**
     * @param array<string, mixed> $defaults
     */
    public function __construct(string $file, array $defaults = [])
    {
        $this->file = $file;
        $this->setDefaults($defaults);
    }

    public function __destruct()
    {
        $this->closeFile();
    }

    /**
     * Override this method and open handle yourself if you want to
     * reposition or load some extra columns on the top.
     *
     * @param string $mode 'r' or 'w'
     */
    public function openFile(string $mode = 'r'): void
    {
        if (!$this->handle) {
            $this->handle = fopen($this->file, $mode);
            if ($this->handle === false) {
                throw (new Exception('Cannot open CSV file'))
                    ->addMoreInfo('file', $this->file)
                    ->addMoreInfo('mode', $mode);
            }
        }
    }

    public function closeFile(): void
    {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
            $this->header = null;
        }
    }

    /**
     * Returns one line of CSV file as array.
     *
     * @return ($reindexWithHeader is true ? array<string, string> : array<int, string>)|null
     */
    public function getLine(bool $reindexWithHeader): ?array
    {
        $data = fgetcsv($this->handle, 0, $this->delimiter, $this->enclosure, $this->escapeChar);
        if ($data === false) {
            return null;
        }

        ++$this->line;

        if ($reindexWithHeader) {
            $data = array_combine($this->header, $data);
        }

        return $data;
    }

    /**
     * Writes array as one record to CSV file.
     *
     * @param array<int, string> $data
     */
    public function putLine(array $data): void
    {
        $ok = fputcsv($this->handle, $data, $this->delimiter, $this->enclosure, $this->escapeChar);
        if ($ok === false) {
            throw new Exception('Cannot write to CSV file');
        }
    }

    /**
     * When load operation starts, this will open file and read
     * the first line. This line is then used to identify columns.
     */
    public function loadHeader(): void
    {
        $this->openFile('r');

        $header = $this->getLine(false);
        --$this->line; // because we don't want to count header line

        $this->initializeHeader($header);
    }

    /**
     * When load operation starts, this will open file and read
     * the first line. This line is then used to identify columns.
     */
    public function saveHeader(Model $model): void
    {
        $this->openFile('w');

        $header = [];
        foreach (array_keys($model->getFields()) as $name) {
            if ($model->idField && $name === $model->idField) {
                continue;
            }

            $header[] = $name;
        }

        $this->putLine($header);

        $this->initializeHeader($header);
    }

    /**
     * Remembers $this->header so that the data can be easier mapped.
     *
     * @param array<int, string> $header
     */
    public function initializeHeader(array $header): void
    {
        // removes forbidden symbols from header (field names)
        $this->header = array_map(static function (string $name): string {
            return preg_replace('~[^a-z0-9_-]+~i', '_', $name);
        }, $header);
    }

    public function tryLoad(Model $model, $id): ?array
    {
        $model->assertIsModel();

        if ($id !== self::ID_LOAD_ANY) {
            throw new Exception('CSV Persistence does not support other than LOAD ANY mode'); // @TODO
        }

        if (!$this->mode) {
            $this->mode = 'r';
        } elseif ($this->mode === 'w') {
            throw new Exception('Currently writing records, so loading is not possible');
        }

        if (!$this->handle) {
            $this->loadHeader();
        }

        $data = $this->getLine(true);
        if ($data === null) {
            return null;
        }

        $data = $this->typecastLoadRow($model, $data);
        if ($model->idField) {
            $data[$model->idField] = $this->line;
        }

        return $data;
    }

    /**
     * @return \Traversable<array<string, mixed>>
     */
    public function prepareIterator(Model $model): \Traversable
    {
        if (!$this->mode) {
            $this->mode = 'r';
        } elseif ($this->mode === 'w') {
            throw new Exception('Currently writing records, so loading is not possible');
        }

        if (!$this->handle) {
            $this->loadHeader();
        }

        while (true) {
            $data = $this->getLine(true);
            if ($data === null) {
                break;
            }

            $data = $this->typecastLoadRow($model, $data);
            if ($model->idField) {
                $data[$model->idField] = $this->line;
            }

            yield $data;
        }
    }

    protected function insertRaw(Model $model, array $dataRaw)
    {
        if (!$this->mode) {
            $this->mode = 'w';
        } elseif ($this->mode === 'r') {
            throw new Exception('Currently reading records, so writing is not possible');
        }

        if (!$this->handle) {
            $this->saveHeader($model->getModel(true));
        }

        $line = [];
        foreach ($this->header as $name) {
            $line[] = $dataRaw[$name];
        }

        $this->putLine($line);

        return $model->idField ? $dataRaw[$model->idField] : null;
    }

    /**
     * Export all DataSet.
     *
     * @param array<int, string>|null $fields
     *
     * @return array<int, array<string, mixed>>
     */
    public function export(Model $model, array $fields = null): array
    {
        $data = [];
        foreach ($model as $entity) {
            $entityData = $entity->get();
            $data[] = $fields !== null ? array_intersect_key($entityData, array_flip($fields)) : $entityData;
        }

        // need to close file otherwise file pointer is at the end of file
        $this->closeFile();

        return $data;
    }
}
