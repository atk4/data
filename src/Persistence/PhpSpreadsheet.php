<?php
declare(strict_types=1);

namespace atk4\data\Persistence;

use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Persistence;
use Generator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use atk4\data\Exception;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use Throwable;

class PhpSpreadsheet extends Persistence
{
    //

    const OP_READ = -1;
    const OP_WAIT = 0;
    const OP_WRITE = 1;

    /**
     * Defines the direction of the operations
     *
     * Can be defined only on the first operation
     *
     * loading data => OP_READ
     * insert data => OP_WRITE
     *
     * not using this flag, create issues like load 3,insert 1, load 1 record ( all messed up )
     *
     * @var int
     */
    private $io_direction = self::OP_WAIT;

    // use constants for autocomplete
    // Persistence_Spreadsheet::READ...
    // Persistence_Spreadsheet::WRITE...

    /* ALLOWED READ TYPE */

    const READ_TYPE_XLS  = 'Xls';

    const READ_TYPE_XLSX = 'Xlsx';

    const READ_TYPE_XML  = 'Xml';

    const READ_TYPE_ODS  = 'Ods';

    const READ_TYPE_SLK  = 'Slk';

    const READ_TYPE_GNU  = 'Gnumeric';

    const READ_TYPE_CSV  = 'Csv';

    const READ_TYPE_HTML = 'Html';

    /**
     * Name of the file to read.
     *
     * @var string
     */
    private $reader_file;

    /**
     * Name of the file to read.
     *
     * one of the constant starting with READ_TYPE...
     *
     * @var string
     */
    private $reader_type;

    /**
     * @var IReader
     */
    private $IReader;

    /* ALLOWED WRITE TYPE */

    const WRITE_TYPE_XLS  = 'Xls';

    const WRITE_TYPE_XLSX = 'Xlsx';

    const WRITE_TYPE_ODS  = 'Ods';

    const WRITE_TYPE_CSV  = 'Csv';

    const WRITE_TYPE_HTML = 'Html';

    const WRITE_TYPE_PDF  = 'Pdf';

    /**
     * Name of the file to read.
     *
     * @var string
     */
    private $writer_file;

    /**
     * Type of the file to read.
     *
     * @var string
     */
    private $writer_type;

    /**
     * @var IWriter
     */
    private $IWriter;

    /**
     * The spreadsheet
     *
     * @var Spreadsheet
     */
    private $spreadsheet = null;

    /**
     * The workbook selected, allowed only for : Xls, Xlsx
     *
     * @var int
     */
    private $sheet_index = 1;

    /**
     * Pointer for reading
     *
     * @var int
     */
    private $sheet_row_index = 1;

    /**
     * Not used yet
     *
     * @TODO will be used for multirow headers
     *
     * @var int
     */
    private $header_row_offset = 1;

    /**
     * @var array
     */
    private $column_header_names = [];

    /**
     * PhpOffice/Spreadsheet Writer Pdf class
     *
     * is mandatory only if choose to write pdf
     *
     * must be one of : Tcpdf | Mpdf | Dompdf
     *
     * Library    Downloadable from                      PhpSpreadsheet writer
     * TCPDF      https://github.com/tecnickcom/tcpdf    Tcpdf
     * mPDF       https://github.com/mpdf/mpdf           Mpdf
     * Dompdf     https://github.com/dompdf/dompdf       Dompdf
     *
     * @var string|null
     */
    private $PDFWriterClass;

    /**
     * Persistence_Spreadsheet constructor.
     *
     * @param string|null $reader_file
     * @param string|null $writer_file
     * @param int         $sheet_index index of the workbook to read, allowed only for : Xls, Xlsx
     *
     * @param string|null $PDFWriterClass
     *
     * @throws Exception
     */
    public function __construct($reader_file, $writer_file = null, $sheet_index = 1, $PDFWriterClass = null)
    {
        if(!is_null($PDFWriterClass) && !in_array($PDFWriterClass,['Tcpdf','Mpdf','Dompdf']))
        {
            throw new Exception('PDFWriterClass must be one of : Tcpdf | Mpdf | Dompdf');
        }

        $this->PDFWriterClass = $PDFWriterClass;

        $this->reader_file = $reader_file;
        $this->_prepareIReader();

        $this->writer_file = is_null($writer_file) ? $reader_file : $writer_file;
        $this->_prepareIWriter();

        $this->sheet_index = $sheet_index;
    }

    /**
     * Destructor. close files correctly.
     */
    public function __destruct()
    {
        if ($this->spreadsheet === null)
        {
            return;
        }

        $this->_spreadsheetRead();
        $this->_spreadsheetWrite();

        /**
         * Clearing a Workbook from memory
         * The PhpSpreadsheet object contains cyclic references (e.g. the workbook is linked to the worksheets, and
         * the worksheets are linked to their parent workbook) which cause problems when PHP tries to clear the objects
         * from memory when they are unset(), or at the end of a function when they are in local scope. The result of
         * this is "memory leaks", which can easily use a large amount of PHP's limited memory.
         *
         * This can only be resolved manually: if you need to unset a workbook, then you also need to "break"
         * these cyclic references before doing so.
         *
         * PhpSpreadsheet provides the disconnectWorksheets() method for this purpose.
         */

        //$this->spreadsheet->disconnectWorksheets();
    }

    private function setIODirectionOrThrow($direction)
    {
        if($this->io_direction === static::OP_WAIT)
        {
            $this->io_direction = $direction;
            return;
        }

        if($this->io_direction !== $direction)
        {
            throw new Exception(['IO Direction already set and cannot be changed','direction' => $direction]);
        }
    }

    /**
     * Check IReader requirements
     *
     * Check only the requirements for iWriter, will be used later
     *
     * @throws Exception
     */
    private function _prepareIReader()
    {
        $type = $this->_getReaderTypeByFile($this->reader_file);

        $this->reader_type = $type;
    }

    /**
     * Check iWriter requirements
     *
     * Check only the requirements for iWriter, will be used later
     *
     * @throws Exception
     */
    private function _prepareIWriter()
    {
        $type = $this->_getWriterTypeByFile($this->writer_file);

        $this->writer_type = $type;

        if($type === static::WRITE_TYPE_PDF)
        {
            $this->_setPDFWriterClass($this->PDFWriterClass);
        }

        //$this->writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet,$this->filetype);
    }


    /**
     * @param string $file
     *
     * @return string
     * @throws Exception
     */
    private function _getWriterTypeByFile($file): string
    {
        $ext = strtolower(substr(strrchr($file, '.'), 1));

        if (empty($ext)) {
            throw new Exception('File extension not found', 0);
        }

        $type = '';

        switch ($ext) {
            case 'xls': // Excel (BIFF) Spreadsheet
            case 'xlt': // Excel (BIFF) Template
                $type = static::WRITE_TYPE_XLS;
                break;

            case 'xlsx': // Excel (OfficeOpenXML) Spreadsheet
            case 'xlsm': // Excel (OfficeOpenXML) Macro Spreadsheet (macros will be discarded)
            case 'xltx': // Excel (OfficeOpenXML) Template
            case 'xltm': // Excel (OfficeOpenXML) Macro Template (macros will be discarded)
                $type = static::WRITE_TYPE_XLSX;
                break;

            case 'ods': // Open/Libre Offic Calc
            case 'ots': // Open/Libre Offic Calc Template
                $type = static::WRITE_TYPE_ODS;
                break;

            case 'csv':
                $type = static::WRITE_TYPE_CSV;
                break;

            case 'htm':
            case 'html':
                $type = static::WRITE_TYPE_CSV;
                break;

            case 'pdf':
                $type = static::WRITE_TYPE_PDF;
                break;
        }

        if (empty($type)) {
            throw new Exception('File type not allowed for IWriter', 0);
        }

        return $type;
    }

    /**
     * @param string $file
     *
     * @return string
     * @throws Exception
     */
    private function _getReaderTypeByFile($file)
    {
        $ext = strtolower(substr(strrchr($file, '.'), 1));

        if (empty($ext)) {
            throw new Exception('File extension not found', 0);
        }

        $type = '';

        switch (strtolower($ext)) {
            case 'xlsx': // Excel (OfficeOpenXML) Spreadsheet
            case 'xlsm': // Excel (OfficeOpenXML) Macro Spreadsheet (macros will be discarded)
            case 'xltx': // Excel (OfficeOpenXML) Template
            case 'xltm': // Excel (OfficeOpenXML) Macro Template (macros will be discarded)
                $type = static::READ_TYPE_XLSX;
                break;

            case 'xls': // Excel (BIFF) Spreadsheet
            case 'xlt': // Excel (BIFF) Template
                $type = static::READ_TYPE_XLS;
                break;

            case 'ods': // Open/Libre Offic Calc
            case 'ots': // Open/Libre Offic Calc Template
                $type = static::READ_TYPE_ODS;
                break;

            case 'slk':
                $type = static::READ_TYPE_SLK;
                break;

            case 'xml': // Excel 2003 SpreadSheetML
                $type = static::READ_TYPE_XML;
                break;

            case 'gnumeric':
                $type = static::READ_TYPE_GNU;
                break;

            case 'htm':
            case 'html':
                $type = static::READ_TYPE_HTML;
                break;

            case 'csv':
                $type = static::READ_TYPE_CSV;
                break;
        }

        if (empty($type)) {
            throw new Exception('File type not allowed for IReader', 0);
        }

        return $type;
    }

    private function _setPDFWriterClass($Class)
    {
        if(is_null($Class) || (!is_null($Class) && !in_array($Class,['Tcpdf','Mpdf','Dompdf'])))
        {
            throw new Exception('PDF writing needs to set PDFClass name in __construct or in saveSheetAs methods, value must be set to one this : Tcpdf | Mpdf | Dompdf');
        }

        $this->PDFWriterClass = '\\PhpOffice\\PhpSpreadsheet\\Writer\\Pdf\\' . $Class;
        IOFactory::registerWriter('Pdf', $this->PDFWriterClass);
    }

    /**
     * Read the spreadsheet if is not already read
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws Exception
     */
    private function _spreadsheetRead()
    {
        if ($this->spreadsheet !== null) return;

        if (file_exists($this->reader_file))
        {
            $this->IReader = IOFactory::createReader($this->reader_type);

            try
            {
                $spreadsheet = $this->IReader->load($this->reader_file);
            }
            catch (Throwable $t)
            {

                $exc_data = [
                    "can't open file for reading " . $this->reader_file,
                    'type' => $this->reader_type,
                ];

                throw new Exception($exc_data, 404, $t);
            }
        }
        else
        {
            $spreadsheet = new Spreadsheet();
        }

        $this->spreadsheet = $spreadsheet;

        // if null = add new sheet after last sheet
        // els if - 1  = add new sheet before first sheet
        // else set to requested index

        if($this->sheet_index === null)
        {
            $newWorkSheet = new Worksheet($spreadsheet, 'atk4 Data');
            $this->spreadsheet->addSheet($newWorkSheet);
            $this->sheet_index = $this->spreadsheet->getSheetCount() - 1;
        }
        else if($this->sheet_index === -1)
        {
            $newWorkSheet = new Worksheet($spreadsheet, 'atk4 Data');
            $this->spreadsheet->addSheet($newWorkSheet, 0);
            $this->sheet_index = 0;
        } else {
            $this->sheet_index--;
        }

        $this->spreadsheet->setActiveSheetIndex($this->sheet_index);

        // if header is set reset row index
        if($this->_setHeader($this->_getRowDataAtIndex(1)))
        {
            $this->_resetRowIndex();
        }
    }

    /**
     * Write spreadsheet to file with defined format type
     *
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    private function _spreadsheetWrite()
    {
        // check if there is something to write
        if (count($this->spreadsheet->getAllSheets()) === 0) {
            return;
        }

        $this->saveSheetAs();
    }

    /**
     * Save persistence with another format
     *
     * @param string|null $filename
     * @param string|null $PdfClass
     *
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function saveSheetAs($filename = null,$PdfClass = null)
    {
        // trigger reading if not already done
        $this->_spreadsheetRead();

        $writer_type = $this->writer_type;
        $writer_file = $this->writer_file;

        if(!is_null($filename))
        {
            $writer_type = $this->_getWriterTypeByFile($filename);
            $writer_file = $filename;

            if($writer_type === static::WRITE_TYPE_PDF && is_null($this->PDFWriterClass))
            {
                $this->_setPDFWriterClass($PdfClass);
            }
        }

        try {

            $this->IWriter = IOFactory::createWriter($this->spreadsheet, $writer_type);
            $this->IWriter->save($writer_file);
        } catch(Throwable $t)
        {
            throw new Exception(["can't open file for writing " . $this->writer_file,'type' => $writer_type],404,$t);
        }

    }

    /* HEADER FUNCTIONS */
    /**
     * Try set header based on model fields
     *
     * @param Model $m
     *
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function _trySetHeaderFromModel(Model $m)
    {
        if ($this->_isHeaderSet()) {
            return;
        }

        $header = [];
        foreach ($m->elements as $name => $field) {

            if (!$field instanceof Field) {
                continue;
            }

            if ($name == $m->id_field) {
                continue;
            }

            $header[] = $name;
        }

        if($this->_setHeader($header))
        {
            $this->_setRowDataAtIndex($header,1);
            $this->_resetRowIndex();
        }
    }

    /**
     * Set $this->column_header_names if not empty
     *
     * @param array<string> $header
     *
     * @return bool
     */
    private function _setHeader($header)
    {
        // if header empty exit <= when file not exists ( blank sheet )
        if(empty($header)) return false;

        // if already set exit
        if(!empty($this->column_header_names)) return false;

        $this->column_header_names = $header;

        return true;
    }

    /**
     * check if header is set or not
     *
     * @return bool
     */
    private function _isHeaderSet()
    {
        return count($this->column_header_names) > 0;
    }

    /**
     * Set active spreadsheet Title
     *
     * @param string $title
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    public function setActiveSheetTitle($title)
    {
        // getActiveSheet() will trigger reading if not already read
        $this->getActiveSheet()->setTitle($title);

        $this->_spreadsheetWrite();
    }

    /**
     * Get active sheet
     *
     * @return Worksheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    private function getActiveSheet()
    {
        // trigger reading if not already read
        $this->_spreadsheetRead();

        return $this->spreadsheet->getActiveSheet();
    }

    /**
     * Get PhpOffice\Spreadsheet (cloned)
     *
     * @return Spreadsheet|null
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function getSpreadsheet()
    {
        // trigger reading if not already read
        $this->_spreadsheetRead();

        if($this->spreadsheet === null) // @TODO verify if this condition is needed
        {
            return null;
        }

        return clone $this->spreadsheet;
    }

    /* ROW FUNCTIONS */

    /**
     * Get Row data from spreadsheet on next row
     *
     * @return array|bool
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */

    private function _getRowDataNext()
    {
        $this->_advanceRowIndex();

        return $this->_getRowDataAtIndex($this->sheet_row_index);
    }
    /**
     * Get Row data from spreadsheet
     *
     * @param int $row_index
     *
     * @return array|bool
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function _getRowDataAtIndex($row_index)
    {
        $col_index_max = $this->_getColumnIndexMax();

        $data          = [];

        // col index in spreadsheet starts from 1
        for($col_index = 1;$col_index <= $col_index_max;$col_index++)
        {
            $cell = $this->getActiveSheet()->getCellByColumnAndRow($col_index, $row_index);
            $data[] = $cell->getValue();
        }

        if ($this->_isRowEmpty($data)) {
            return false;
        }

        return $data;
    }

    /**
     * Reset the row index after header
     */
    private function _resetRowIndex()
    {
        $this->sheet_row_index = 1;
    }

    /**
     * Advance pointer one row of activesheet
     */
    private function _advanceRowIndex()
    {
        $this->sheet_row_index++;
    }

    /**
     * Check if row is empty
     *
     * @param array $data
     *
     * @return bool
     */
    private function _isRowEmpty($data)
    {
        $unique_values = array_unique($data);

        if (count($unique_values) === 1 && $this->_isValueNullOrEmpty($unique_values[0])) {
            return true;
        }

        return false;
    }

    /**
     * Shorthand func check if value is null or empty
     *
     * @param string $value
     *
     * @return bool
     */
    private function _isValueNullOrEmpty($value)
    {
        return empty($value);
    }

    /**
     * @param array<int,mixed> $data
     * @param int $row_index
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    private function _setRowDataAtIndex($data, $row_index)
    {
        foreach ($data as $column_index => $value) {
            $this->getActiveSheet()
                ->getCellByColumnAndRow($column_index + 1, $row_index)
                ->setValue($value);
        }

        $this->_spreadsheetWrite();
    }

    /**
     * Get Row index without header offset = ID of the Row
     *
     * @return int
     */
    private function _getRowDataID()
    {
        return $this->sheet_row_index - $this->header_row_offset;
    }

    /* COLUMN FUNCTION */

    /**
     * Get max index of columns or count($this->column_header_names)
     *
     * @return int
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function _getColumnIndexMax()
    {
        $col_index_max = 0;

        // if header is not set
        if (!empty($this->column_header_names)) {
            $col_index_max = count($this->column_header_names);
        } else {
            // find the index of the last cell with data
            $col_index_max = $this->getActiveSheet()->getHighestColumn(1);
        }

        return $col_index_max;
    }

    /* MODEL FUNCTION */

    /**
     * get row model data
     *
     * @param array<string,mixed> $data
     *
     * @return array
     */
    private function _getModelDataAsRow($data)
    {
        $row = [];
        foreach ($this->column_header_names as $field_name) {
            $row[] = $data[$field_name];
        }

        return $row;
    }

    /* OVERRIDDEN METHODS */

    /**
     * when Model => save => Spreadsheet
     *
     * @param Model $m
     * @param array $data
     *
     * @return int new index
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    public function insert(Model $m, $data)
    {
        $this->setIODirectionOrThrow(static::OP_WRITE);

        $this->_spreadsheetRead();
        $this->_trySetHeaderFromModel($m);

        $data = $this->_getModelDataAsRow($data);

        // write on last row + 1
        $row_index_write = $this->getActiveSheet()->getHighestRow() + 1;

        return $this->_setRowDataAtIndex($data,$row_index_write);
    }

    /**
     * when ALL Spreadsheet => Model
     *
     * @param Model      $m
     * @param array|null $fields
     *
     * @return array
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function export(Model $m, $fields = null)
    {
        // open file if is not open
        $this->_spreadsheetRead();

        // set header if not set by reading
        $this->_trySetHeaderFromModel($m);

        // reset row index if not set
        $this->_resetRowIndex();

        $data = [];

        foreach ($m as $junk) {
            $data[] = $fields ? array_intersect_key($m->get(), array_flip($fields)) : $m->get();
        }

        $this->_spreadsheetWrite();

        return $data;
    }

    /**
     * Prepare iterator.
     *
     * @param Model $m
     *
     * @return Generator
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws Exception
     */
    public function prepareIterator(Model $m)
    {
        $this->setIODirectionOrThrow(static::OP_READ);
        // open file if is not open
        $this->_spreadsheetRead();

        // set header if not set by reading
        $this->_trySetHeaderFromModel($m);

        // reset row index if not set
        $this->_resetRowIndex();

        while (true) {

            // get data and avance to next record
            $data = $this->_getRowDataNext();

            if (!$data) {
                break;
            }

            $data               = $this->typecastLoadRow($m, $data);
            $data[$m->id_field] = $this->_getRowDataID();

            yield $data;
        }

        $this->_resetRowIndex();
    }

    /**
     * Loads any one record.
     *
     * @param Model $m
     *
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    public function loadAny(Model $m)
    {
        $data = $this->tryLoadAny($m);

        if (!$data) {

            $exc_data = [
                'No more records',
                'model' => $m,
            ];

            throw new Exception($exc_data, 404);
        }

        return $data;
    }

    /**
     * Tries to load model and return data record.
     * Doesn't throw exception if model can't be loaded.
     *
     * @param Model $m
     *
     * @return array|null
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    public function tryLoadAny(Model $m)
    {
        $this->setIODirectionOrThrow(static::OP_READ);

        // open file if is not open
        $this->_spreadsheetRead();

        // set header if not set by reading
        $this->_trySetHeaderFromModel($m);

        // get data from row at pointing index
        // if true go the next row
        $data = $this->_getRowDataNext();
        if (!$data) {
            return null;
        }

        $data       = $this->typecastLoadRow($m, $data);
        $data['id'] = $this->_getRowDataID();

        return $data;
    }

    /**
     * Typecasting when load data row.
     *
     * @param Model $m
     * @param array $row
     *
     * @return array
     * @throws Exception
     */
    public function typecastLoadRow(Model $m, $row)
    {
        $id = null;
        if (isset($row[$m->id_field])) {
            // temporary remove id field
            $id = $row[$m->id_field];
            unset($row[$m->id_field]);
        } else {
            $id = null;
        }
        $row = array_combine($this->column_header_names, $row);
        if (isset($id)) {
            $row[$m->id_field] = $id;
        }

        foreach ($row as $key => &$value) {
            if ($value === null) {
                continue;
            }

            if ($f = $m->hasElement($key)) {
                $value = $this->typecastLoadField($f, $value);
            }
        }

        return $row;
    }

    /* OVERRIDDEN BUT DISABLED */

    /**
     * Updates record in data array and returns record ID.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param array  $data
     * @param string $table
     *
     * @throws Exception
     */
    public function update(Model $m, $id, $data, $table = null)
    {
        throw new Exception(['Updating records is not supported in Spreadsheet persistence.']);
    }

    /**
     * Deletes record in data array.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param string $table
     *
     * @throws Exception
     */
    public function delete(Model $m, $id, $table = null)
    {
        throw new Exception(['Deleting records is not supported in Spreadsheet persistence.']);
    }
}
