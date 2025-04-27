<?php

// namespace App\Console\Commands;
namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;

use Illuminate\Support\Facades\Http;

use \Cviebrock\EloquentSluggable\Services\SlugService;

use Backpack\Store\app\Models\Brand;
// use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Supplier;
// use Backpack\Store\app\Models\SupplierProduct;
use Backpack\Store\app\Models\Source;
use Backpack\Store\app\Models\UploadHistory;
use Backpack\Store\app\Jobs\uploadFromXmlSource;


use Illuminate\Support\Facades\Storage;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;


use Backpack\Store\app\Traits\Exchange;

class XmlSource extends Command
{
    use Exchange;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xml:source';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';


    protected $totalRecords = 0;
    protected $totalNew = 0;
    protected $totalUpdated = 0;

    protected $settings = null;
    protected $rules = null;
    protected $stockRules = [];
    protected $cs = null;

    protected $isSuppliersEnabled = false;
    protected $SP_CLASS = null;
    protected $PRODUCT_CLASS = null;
    protected $IS_TEST_MODE = false;
    protected $TEST_ITEMS = -1;

    protected $currentSource = null;
    protected $uploadHistory = null;

    protected $lang = 'en';
    protected $available_languages;
    protected $exchange_rate = 1;

    protected $fieldLetters = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
      parent::__construct();
      $this->isSuppliersEnabled = config('backpack.store.supplier.enable', false);

      $this->SP_CLASS = config('backpack.store.supplier.sp_class', 'Backpack\Store\app\Models\SupplierProduct');
      $this->PRODUCT_CLASS = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');

      $this->IS_TEST_MODE = config('backpack.store.source.test.enable', false);
      $this->TEST_ITEMS = config('backpack.store.source.test.items', -1);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      $sources = Source::active()->get();

      $bar = $this->output->createProgressBar(count($sources));
			$bar->start();

      foreach($sources as $source) {
      	$bar->advance();

        // skip if it's not time yet
        if(config('app.mode') === 'production') {
        // if(true) {
          if($source->type === 'file') {
            // type file
            if($source->updated_at->diffInSeconds($source->last_loading) <= 2) {
              continue;
            }
          }else {
            // type xml_link
            if($source->last_loading && $source->every_minutes && \Carbon\Carbon::now()->diffInMinutes($source->last_loading->addMinute($source->every_minutes), false) > 0) {
              continue;
            }
          }
        }

        // update loading timestamp
        $source->last_loading = \Carbon\Carbon::now();
        $source->save();

          try {
            if($source->type === 'file') {
              $this->loadExcelFile($source);
            }else {
              $this->loadFromXml($source);
            }
          }catch (\Exception $e) {
            \Log::channel('xml')->error($e->getMessage());
            $this->setStatusUploadHistory('error');
          }
      }

			$bar->finish();
    }
      
    /**
     * Method isDataRow
     *
     * @param $row $row [explicite description]
     *
     * @return void
     */
    private function isDataRow($sheet, $rowIndex) {
      $rowData = $sheet->rangeToArray("A$rowIndex:" . $sheet->getHighestColumn() . $rowIndex, null, true, false);
      $filledCells = count(array_filter($rowData[0], fn($value) => !empty($value)));

      if($filledCells < 2) {
        return false;
      }

      $price = $sheet->getCell($this->getFieldLetter('price').$rowIndex)->getValue();

      if(!empty($price) && preg_match('/\d/', $price)) {
        return true;
      }else {
        return false;
      }      
    }
    
    /**
     * Method isCategoryCell
     *
     * @param $sheet $sheet [explicite description]
     * @param $cellAddress $cellAddress [explicite description]
     *
     * @return void
     */
    private function isCategoryCell($sheet, $rowIndex) {
      return $this->isSpecificalCell('categories', $sheet, $rowIndex);
    }

    
    /**
     * Method isBrandRow
     *
     * @param $row $row [explicite description]
     * @param $rowIndex $rowIndex [explicite description]
     *
     * @return void
     */
    private function isBrandCell($sheet, $rowIndex) {
      return $this->isSpecificalCell('brands', $sheet, $rowIndex);
    }
    

    /**
     * Method isSpecificalCell
     *
     * @param $field_name $field_name [explicite description]
     * @param $sheet $sheet [explicite description]
     * @param $rowIndex $rowIndex [explicite description]
     *
     * @return void
     */
    private function isSpecificalCell($field_name, $sheet, $rowIndex) {
      $structure = $this->settings["{$field_name}_structure"];


      if($structure === 'levels') {
        $settings_level = $this->settings["{$field_name}_level_index"] ?? null;
        $outlineLevel = $sheet->getRowDimension($rowIndex)->getOutlineLevel();
        return (int)$settings_level == $outlineLevel? true: false;
      }else if($structure === 'visual') {
        $cellAddress = $this->getFirstFilledCell($sheet, $rowIndex);
        $row_properties = $this->settings["{$field_name}_visual_id"] ?? null;
        return !empty($row_properties)? $this->testCellStyle($sheet, $cellAddress, $row_properties): null;
      } else {
        return false;
      }

    }

    
    /**
     * Method testCell
     *
     * @param $sheet $sheet [explicite description]
     * @param $cellAddress $cellAddress [explicite description]
     * @param $properties_string $properties_string [explicite description]
     *
     * @return void
     */
    private function testCellStyle($sheet, $cellAddress, $properties_string) {
      if(empty($cellAddress)){
        return false;
      }

      $properties_array = explode(',', $properties_string);
      $style = $sheet->getStyle($cellAddress);

      foreach($properties_array as $property) {

        if($property === 'bold' && !$style->getFont()->getBold()) {
          return false;
        }else if($property === 'italic' && !$style->getFont()->getItalic()) {
          return false;
        }else if($property === 'underline' && !$style->getFont()->getUnderline()) {
          return false;
        }else if(str_starts_with($property, 'size:') && ($size = $this->extractSizeValue($property)) && $style->getFont()->getSize() != $size) {
          return false;
        }else if(str_starts_with($property, 'fill:') && ($fill = $this->extractFillValue($property)) && ($style->getFill()->getFillType() !== 'none' && $style->getFill()->getStartColor()->getRGB() !== $fill)) {
          return false;
        }else if(str_starts_with($property, 'color:') && ($color = $this->extractColorValue($property)) && $style->getFont()->getColor()->getRGB() !== $color) {
          return false;
        }
      }

      return true;
    }

    /**
     * Extracts the hexadecimal color value from a string like "fill:ffffff".
     *
     * @param string $inputString The string to check.
     * @return string|null The hexadecimal color value (without #) if found, otherwise null.
     */
    private function extractFillValue(string $inputString): ?string
    {
        // Check if the string matches the "fill:HEX" pattern (case-insensitive).
        if (preg_match('/^fill:([0-9a-fA-F]{6})$/i', $inputString, $matches)) {
            // Return the captured hexadecimal color value.
            return mb_strtoupper($matches[1]);
        }

        // If the string doesn't match the pattern, return null.
        return null;
    }

    /**
     * Extracts the hexadecimal color value from a string like "color:000000".
     *
     * @param string $inputString The string to check.
     * @return string|null The hexadecimal color value (without #) if found, otherwise null.
     */
    private function extractColorValue(string $inputString): ?string
    {
        // Check if the string matches the "color:HEX" pattern (case-insensitive).
        if (preg_match('/^color:([0-9a-fA-F]{6})$/i', $inputString, $matches)) {
            // Return the captured hexadecimal color value.
            return mb_strtoupper($matches[1]);
        }

        // If the string doesn't match the pattern, return null.
        return null;
    }

    /**
     * Extracts the numeric size value from a string like "size:30".
     *
     * @param string $inputString The string to check.
     * @return int|null The numeric size value if found, otherwise null.
     */
    private function extractSizeValue(string $inputString): ?int
    {
        return stripos($inputString, 'size:') === 0
            ? (int)preg_replace('/[^0-9]/', '', substr($inputString, strlen('size:')))
            : null;
    }


        
    /**
     * Method getRowType
     *
     * @param $sheet $sheet [explicite description]
     * @param $rowIndex $rowIndex [explicite description]
     *
     * @return void
     */
    private function getRowType($sheet, $rowIndex) {
      if($this->isDataRow($sheet, $rowIndex)) {
        return 'data';
      }elseif($this->isCategoryCell($sheet, $rowIndex)) {
        return 'category';
      }elseif($this->isBrandCell($sheet, $rowIndex)) {
        return 'brand';
      }else {
        return 'unknown';
      }
    }


    
    /**
     * Method getCategoryValue
     *
     * @param $sheet $sheet [explicite description]
     *
     * @return void
     */
    private function getCategoryValue($sheet, $rowIndex) {
      $cellAddress = $this->getFirstFilledCell($sheet, $rowIndex);
      $structure = $this->settings['categories_structure'];

      if($structure === 'visual' || $structure === 'levels') {
        $category = $sheet->getCell($cellAddress)->getValue();
        return mb_trim($category);
      } else {
        return null;
      }
    }

    
    /**
     * Method getBrandValue
     *
     * @param $sheet $sheet [explicite description]
     *
     * @return void
     */
    private function getBrandValue($sheet, $rowIndex) {
      $cellAddress = $this->getFirstFilledCell($sheet, $rowIndex);
      $structure = $this->settings['brands_structure'];

      if($structure === 'visual' || $structure === 'levels') {
        $brand = $sheet->getCell($cellAddress)->getValue();
        return mb_trim($brand);
      } else {
        return null;
      }
    }
    
    /**
     * Method getCellValue
     *
     * @param $sheet $sheet [explicite description]
     * @param $rowIndex $rowIndex [explicite description]
     * @param $name $name [explicite description]
     *
     * @return void
     */
    private function getCellValue($sheet, $rowIndex, $name) {
      if(!$this->getFieldLetter($name)) {
        return null;
      }

      $data = $sheet->getCell($this->getFieldLetter($name).$rowIndex)->getValue();
      return empty($data)? null: mb_trim($data);
    }
    
    /**
     * Method getCellAddress
     *
     * @param $rowIndex $rowIndex [explicite description]
     * @param $name $name [explicite description]
     *
     * @return void
     */
    private function getCellAddress($rowIndex, $name) {
      return $this->getFieldLetter($name).$rowIndex;
    }

    /**
     * Method getFirstFilledCell
     *
     * @param $sheet $sheet [explicite description]
     *
     * @return void
     */
    private function getFirstFilledCell($sheet, $rowIndex) {
      $firstFilledCellAddress = null;

      foreach ($sheet->getColumnIterator() as $column) {
        $cellAddress = $column->getColumnIndex() . $rowIndex; // Например, "A2", "B2"
        $cellValue = $sheet->getCell($cellAddress)->getValue();

        if (!empty($cellValue)) {
          $firstFilledCellAddress = $cellAddress;
          break;
        }
      } 

      return $firstFilledCellAddress;
    }
    
        
    /**
     * Method getImageInCell
     *
     * @param $sheet $sheet [explicite description]
     * @param string $cellAddress [explicite description]
     *
     * @return Drawing
     */
    function getImageInCell($sheet, string $cellAddress): ?Drawing {
      foreach ($sheet->getDrawingCollection() as $drawing) {
          if ($drawing instanceof Drawing && $drawing->getCoordinates() === $cellAddress) {
              return $drawing; // Нашли нужное изображение
          }
      }
      return null; // Картинки нет
    }

    /**
     * Method loadExcelFile
     *
     * @param $source $source [explicite description]
     *
     * @return void
     */
    private function loadExcelFile($source) {
      $this->bootSource($source);
      
      $sheet = $this->getExcelDataFromFile($source->file);
      $last_row = isset($this->settings['last_row']) && !empty($this->settings['last_row'])? $this->settings['last_row']: null;
      $highestRow = $last_row? $last_row : $sheet->getHighestRow();

      $this->createUploadHistory();
      
      // $this->getImagesFromExcel($sheet);

      if($this->IS_TEST_MODE) {
        // if $TEST_ITEMS === -1 it means all items
        $this->totalRecords = $this->TEST_ITEMS === -1? $highestRow: $this->TEST_ITEMS;
      }else {
        $this->totalRecords =  $highestRow;
      }
      
      $this->totalUploadHistory($this->totalRecords);

      $current_brand = null;
      $current_category = null;

      foreach ($sheet->getRowIterator() as $row) {
        $rowIndex = $row->getRowIndex();

        if($rowIndex > $this->totalRecords) {
          return;
        }

        $first_row_index = (isset($this->settings['first_row']) && !empty($this->settings['first_row'])) 
          ? (int)$this->settings['first_row'] 
          : 1;
        
        // dd('here', $rowIndex, $first_row_index);
        if($rowIndex < $first_row_index) {
          continue;
        }

        // Detect and set categories and brands
        $row_type = $this->getRowType($sheet, $rowIndex);

        if($row_type === 'unknown') {
          $this->info('Unknown row: ' . $rowIndex);
          continue;
        }else if($row_type === 'category') {
          $this->info('Category row: ' . $rowIndex);
          $category = $this->getCategoryValue($sheet, $rowIndex);
          $current_category = !empty($category)? $category: $current_category;
          continue;
        }else if($row_type === 'brand') {
          $this->info('Brand row:' . $rowIndex);
          $brand = $this->getBrandValue($sheet, $rowIndex);
          $current_brand = !empty($brand)? $brand: $current_brand;
          continue;
        }

        // dd($current_category, $current_brand);

        $current_category = $this->settings['categories_structure'] === 'column'? $this->getCellValue($sheet, $rowIndex, 'category'): $current_category;
        $current_brand = $this->settings['brands_structure'] === 'column'? $this->getCellValue($sheet, $rowIndex, 'brand'): $current_brand;

        // Create product array
        $excel_product = [
          //
          'category' => $current_category,
          'brand' => $current_brand,
          //
          'name' => $this->getCellValue($sheet, $rowIndex, 'name'),
          'inStock' => $this->getCellValue($sheet, $rowIndex, 'inStock'),
          'code' => $this->getCellValue($sheet, $rowIndex, 'code'),
          'barcode' => $this->getCellValue($sheet, $rowIndex, 'barcode'),
          'price' => $this->getCellValue($sheet, $rowIndex, 'price')
        ];

        // Format price data
        $excel_product['price'] = !empty($excel_product['price'])? $this->toFloat($excel_product['price']): null;

        // Set image
        // $cellAddress = $this->getCellAddress($rowIndex, 'image');
        // $drawing = $this->getImageInCell($sheet, $cellAddress);

        if($this->validateData($excel_product)) {
          // TRY TO FIND EXISTE PRODUCT
          try {
            $response = $this->updateOrCreateItem($excel_product);
            $this->updateUploadHistory($response);
          }catch(\Exception $e) {
            $this->errorUploadHistory();
				    \Log::channel('xml')->error($e->getMessage());
            // throw new \Exception $e;
          }
        }else {
          $this->processedUploadHistory();
          continue;
        }
        
      }

      $this->setStatusUploadHistory('done');
    }


    // private function storeImage($drawing) {
    //   // dd('getImageResource', $drawing->getImageResource());


    //   $zipReader = fopen($drawing->getPath(),'r');
    //   $file = '';
    //   while (!feof($zipReader)) {
    //       $file .= fread($zipReader,1024);
    //   }
    //   fclose($zipReader);
    //   $extension = $drawing->getExtension();

    //   // $path = $drawing->getPath();
    //   // $name = $drawing->getName();
    //   // $extension = $drawing->getExtension();

    //   // $file = file_get_contents($path);

    //   $disk = 'public';
    //   $folder = 'excel-test';

    //   // 0. Make the image
    //   $image = \Image::make($file)->encode('png');

    //   // 1. Generate a filename.
    //   $filename = md5($file.time()).'.png';

    //   // 2. Store the image on disk.
    //   \Storage::disk($disk)->put($folder . '/' . $filename, $image->stream());


    //   $image_path = \Storage::disk($disk)->path($folder . '/' . $filename);

    //   dd($image_path);
    // }
    
    /**
     * Method toFloat
     *
     * @param $value $value [explicite description]
     *
     * @return void
     */
    private function toFloat($value) {
      if (is_numeric($value)) {
          return (float) $value; // Если уже число, просто приводим к float
      }
  
      // Удаляем пробелы и неразрывные пробелы
      $value = str_replace([" ", "\xc2\xa0"], "", $value); 
  
      // Если в числе есть запятая и точка, определяем, какой символ — десятичный
      if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
          if (strrpos($value, ',') > strrpos($value, '.')) {
              // Последняя запятая – десятичный разделитель, убираем точки (разделители тысяч)
              $value = str_replace('.', '', $value);
              $value = str_replace(',', '.', $value);
          } else {
              // Последняя точка – десятичный разделитель, убираем запятые (разделители тысяч)
              $value = str_replace(',', '', $value);
          }
      } elseif (strpos($value, ',') !== false) {
          // Если есть только запятая, заменяем её на точку (европейский стиль)
          $value = str_replace(',', '.', $value);
      }
  
      return floatval($value);
    }
    
    /**
     * Method getExcelDataFromFile
     *
     * @param $file_path $file_path [explicite description]
     *
     * @return void
     */
    private function getExcelDataFromFile($file_path) {
      $path = Storage::disk('excel')->path($file_path);

      $spreadsheet = IOFactory::load($path);
      $sheet = $spreadsheet->getActiveSheet();

      return $sheet;
    }
    
    /**
     * loadFromXml
     *
     * @param  mixed $source
     * @return void
     */
    private function loadFromXml($source) {
      $this->bootSource($source);
      
      $xml = $this->getXMLCatalog($source->link);

      $item = array_reduce(explode('->', $this->settings['item']), function($model, $property) {
        return $model->{$property};
      }, $xml);

      if($this->IS_TEST_MODE) {
        // if $TEST_ITEMS === -1 it means all items
        $this->totalRecords = $this->TEST_ITEMS === -1? count($item): $this->TEST_ITEMS;
      }else {
        $this->totalRecords = count($item);
      }
      
      $this->totalUploadHistory($this->totalRecords);

      for($i = 0; $i < $this->totalRecords; $i++){
        
        // Get Attributes
        // foreach($item[$i]->attributes() as $k => $v) {
        //     \Log::info($k . ' = ' . $v);
        // }

        $xml_product = [
          'category' => $item[$i]->{$this->settings['fieldCategory']}->__toString(),
          'name' => $item[$i]->{$this->settings['fieldName']}->__toString(),
          'brand' => $item[$i]->{$this->settings['fieldBrand']}->__toString(),
          'inStock' => $item[$i]->{$this->settings['fieldInStock']}->__toString(),
          'code' => $item[$i]->{$this->settings['fieldCode']}->__toString() ?? null,
          'barcode' => $item[$i]->{$this->settings['fieldBarcode']}->__toString() ?? null,
          'price' => $item[$i]->{$this->settings['fieldPrice']}->__toString()
        ];

        if(isset($this->settings['fieldImage']) && !empty($this->settings['fieldImage'])) {
          $xml_product['images'] = $item[$i]->{$this->settings['fieldImage']};
        }

        // \Log::info(print_r($xml_product, true));

        if($this->validateData($xml_product)) {
          // TRY TO FIND EXISTE PRODUCT
          try {
            $response = $this->updateOrCreateItem($xml_product);
            $this->updateUploadHistory($response);
          }catch(\Exception $e) {
            $this->errorUploadHistory();
				    \Log::channel('xml')->error('updateOrCreateItem error: ' . $e->getMessage());
            // throw new \Exception $e;
            continue;
          }
        }else {
          $this->processedUploadHistory();
          continue;
        }

      }

      $this->setStatusUploadHistory('done');
    }
        
    /**
     * setStatusUploadHistory
     *
     * @param  mixed $status
     * @return void
     */
    private function setStatusUploadHistory($status) {
      $uh = $this->uploadHistory;
      $uh->status = $status;
      $uh->save();
    }
    
    /**
     * createUploadHistory
     *
     * @return void
     */
    private function createUploadHistory() {
      $uh = new UploadHistory;
      $uh->source_id = $this->currentSource->id;
      $uh->status = 'pending';
      $uh->rules = $this->currentSource->rules;
      $uh->save();
      //
      $this->uploadHistory = $uh;
    }
    
    /**
     * processedUploadHistory
     *
     * @return void
     */
    private function processedUploadHistory() {
      $uh = $this->uploadHistory;
      $uh->processed_items += 1;
      $uh->save();
    }
    
    /**
     * errorUploadHistory
     *
     * @return void
     */
    private function errorUploadHistory() {
      $uh = $this->uploadHistory;
      $uh->processed_items += 1;
      $uh->error_items += 1;
      $uh->save();
    }

        
    /**
     * totalUploadHistory
     *
     * @param  mixed $total
     * @return void
     */
    private function totalUploadHistory($total) {
      $uh = $this->uploadHistory;
      $uh->total_items = $total;
      $uh->save();
    }
    
    
    /**
     * updateUploadHistory
     *
     * @param  mixed $data
     * @return void
     */
    private function updateUploadHistory($data) {
      $uh = $this->uploadHistory;
      $uh->processed_items += 1;

      if($data === 'update') {
        $uh->updated_items += 1;
      }else if($data === 'create') {
        $uh->new_items += 1;
      }

      $uh->save();
    }

    /**
     * updateOrCreateItem
     *
     * @param  mixed $data
     * @return void
     */
    private function updateOrCreateItem($data) {

      if($this->isSuppliersEnabled && $this->currentSource->supplier) {
        return $this->updateOrCreateSupplierProduct($data);
      }else {
        return $this->updateOrCreateProduct($data);
      }

    }


    /**
     * updateOrCreateProduct
     * 
     * if suppliers disabled and product has only single fields: price, in_stock, code etc.
     *
     * @param  mixed $data
     * @return void
     */
    private function updateOrCreateProduct($data) {
      $update_or_create = 'update';

      $product = $this->PRODUCT_CLASS::where('id', '>', 0);

      $function_name = !empty($data['code']) && !empty($data['barcode'])? 'orWhere': 'where';

      if(!empty($data['code'])) {
        $product = $product->where('code', $data['code'])
            ->orWhere('barcode', $data['code']);
      }
      if(!empty($data['barcode'])) {
        $product = $product->{$function_name}('code', $data['barcode'])
            ->orWhere('barcode', $data['barcode']);
      }

      $product = $product
                  ->orWhereRaw("LOWER(`name->{$this->lang}`) LIKE ? ",[trim(strtolower($data['name'])).'%'])
                  ->first();

      if(!$product) {
        $update_or_create = 'create';
        $product = $this->createProduct($data);
      }

      // Update Code, Barcode, amount, price
      $this->setProductData($product, $data);

      // Save product
      $product->save();

      // Set category to product
      $this->attachProductCategory($product, $data);

      return $update_or_create;
    }

    
    /**
     * updateOrCreateSupplierProduct
     * 
     * If Multiple suppliers enabled
     *
     * @param  mixed $data
     * @return void
     */
    private function updateOrCreateSupplierProduct($data) {
      $update_or_create = 'update';
      
      $sp = $this->SP_CLASS::
              where('supplier_id', $this->currentSource->supplier_id);

      // Find already presented products's supplier in DB
      $sp = $sp->where(function($query) use($data) {
        $function_name = !empty($data['code']) && !empty($data['barcode'])? 'orWhere': 'where';

        if(!empty($data['code'])) {
          $query->where(function($query) use($data) {
            $query->where('code', $data['code'])
                  ->orWhere('barcode', $data['code']);
          });
        }

        if(!empty($data['barcode'])) {
          $query->{$function_name}(function($query) use($data) {
                  $query->where('code', $data['barcode'])
                        ->orWhere('barcode', $data['barcode']);
          }); 
        }

      });

      // Get first supplier was found
      $sp = $sp->first();

      // No supplier it means no product in DB. Create New Product
      if(!$sp) {
        $update_or_create = 'create';

        $sp = new $this->SP_CLASS;

        // Is Not SupplierProduct means is not Product also
        $product = $this->createProduct($data);

        // Save product
        $product->save();

        // Set category to product
        $this->attachProductCategory($product, $data);
      }else {
        $product = $sp->product;
        $this->forceUpdateFields($product, $data);
      }


      // Update Code, Barcode, inStock, price
      $this->setSupplierData($sp, $data);
    
      // Attach Supplier Product to Product
      $sp->product_id = $product->id;
      $sp->checked_at = time();
      
      $sp->saveWithEvent();

      return $update_or_create;
    }
    
    
    /**
     * Method forceUpdateFields
     *
     * @param $product $product [explicite description]
     * @param $data $data [explicite description]
     *
     * @return void
     */
    private function forceUpdateFields($product, $data) {
      if(!isset($this->settings['forceUpdate']) || empty($this->settings['forceUpdate'])) {
        return;
      }

      $was_updated = false;

      // IMAGES UPDATE
      if(in_array('image', $this->settings['forceUpdate'])) {

        $was_cleared = $this->clearBrokenImages($product);

        if($was_cleared) {
          try {
            $this->setProductImage($product, $data);
          }catch(\Exception $e) {
            throw new \Exception('Set Image Error: ' . $e->getMessage());
          }
  
          $was_updated = true;
        }
      }
      
      // NAME UPDATE
      if(in_array('name', $this->settings['forceUpdate'])) {
        $this->setProductName($product, $data);

        $was_updated = true;
      }

      // BRAND UPDATE
      if(in_array('brand', $this->settings['forceUpdate'])) {
        // no realisation

        $was_updated = true;
      }

      // CATEGORY UPDATE
      if(in_array('category', $this->settings['forceUpdate'])) {
        // no realisation

        $was_updated = true;
      }

      if($was_updated){
        $product->save();
      }
    }

    
    /**
     * Method clearBrokenImages
     *
     * @param &$product $product [explicite description]
     *
     * @return boolean
     */
    private function clearBrokenImages(&$product){
      if(!is_array($product->images)) {
          $product->images = null;
          $this->line('images is not array, set to null: ' . $product->id);
          \Log::channel('xml')->info('images is not array, set to null: ' . $product->id);
          return true;
      }

      $good_images = array_filter($product->images, function($item) {
          if(isset($item['src']) && !empty($item['src'])) {
              return $this->checkRemoteImage($item['src']);
          }else {
              return false;
          }
      });

      if(!count($good_images)) {
          $this->line('NO VALID IMAGES for product: ' . $product->id);
          \Log::channel('xml')->error('NO VALID IMAGES for product: ' . $product->id);
          $product->images = null;
          return true;
      }else {
        return false;
      }
    }

    
    /**
     * Method checkRemoteImage
     *
     * @param $url $url [explicite description]
     *
     * @return void
     */
    public function checkRemoteImage($url) {
      $base_path = config('backpack.store.product.image.base_path', '/');
      $image_url = $base_path . $url;

      $response = Http::get($image_url);

      if($response->ok()) {
        $this->info('file exist: ' . $image_url);
        return true;
      }else {
        $this->error('file is broken: ' . $image_url);
        \Log::channel('xml')->error('file is broken: ' . $image_url);
        return false;
      }
    }
    
    /**
     * createProduct
     *
     * @return void
     */
    private function createProduct($data) {
      $product = new $this->PRODUCT_CLASS;
      $this->setProductInitFields($product);
      
      $this->setProductName($product, $data);
      
      try {
        $this->setProductImage($product, $data);
      }catch(\Exception $e) {
        throw new \Exception('Set Image Error: ' . $e->getMessage());
      }

      try {
        // Set brand to product
        $this->attachProductBrand($product, $data);
      }catch(\Exception $e) {
        throw new \Exception('Set Brand Error: ' . $e->getMessage());
      }

      return $product;
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /**
     * getInStock
     *
     * @param  mixed $data
     * @return void
     */
    private function getInStock($data) {
      $in_stock = 0;

      // \Log::info('getInStock');
      // \Log::info(print_r($data, true)); 
      // \Log::info(print_r($this->stockRules, true));    

      if(empty($this->stockRules)) {
        $in_stock = intval($data['inStock']);
        // \Log::info('Empty inStockRules' . $in_stock);  
        return $in_stock;
      }

      // If Product has no isStock data
      $inStock = $data['inStock'] === null? 'null': $data['inStock'];
      
      $rule = $this->stockRules[$inStock] ?? null;

      // if rule not exists set value to zero
      if($rule === null) {
        $in_stock = 0;
      }else {
        $in_stock = intval($rule['value']);  
      }

      // \Log::info('Not Empty inStockRules' . $in_stock);
      return $in_stock;
    }
    
    /**
     * getPrice
     *
     * @param  mixed $data
     * @return void
     */
    private function getPrice($data) {
      $exchange_rate = 1;

      // EXCHANGE CURRENCY
      if(isset($this->rules['exchange'][0]) && !empty($this->rules['exchange'][0])) {
        $rule = $this->rules['exchange'][0];
        
        if($this->isRuleForProduct($rule, $data)) {
          $exchange_rate = $this->exchange_rate;
        }

        if(!empty($rule['exchange_coff'])) {
          $exchange_rate = $exchange_rate * $rule['exchange_coff'];
        }
      }

      $overPrice = 1;

      // OVERPRICE
      if(isset($this->rules['overprice']) && !empty($this->rules['overprice'])) {
        foreach($this->rules['overprice'] as $rule) {
          // dd($rule);
          if($this->isRuleForProduct($rule, $data)) {
            $overPrice = $rule['overprice'];
          }
        }
      }
      
      return ceil((float)$data['price'] * $exchange_rate * $overPrice);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * setProductBrand
     *
     * @param  mixed $product
     * @param  mixed $data
     * @return void
     */
    private function attachProductBrand(&$product, $data) {
      if(empty($data['brand'])) {
        return;
      }

      $langs_list = array_keys($this->available_languages);
      // Try find brand by name in database
      $brand = Brand::
          where(function($query) use ($data, $langs_list){
            foreach($langs_list as $index => $lang_key) {
              $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
              // $query->{$function_name}("LOWER(`name->{$lang_key}`) LIKE ? ",[trim(strtolower($data['brand']))]);
              // dd(mb_trim(mb_strtolower($data['brand'])));
              $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ',['"' . mb_trim(mb_strtolower($data['brand'])) . '"']);
            }
          })
        // ->orWhereRaw("LOWER(`name->uk`) LIKE ? ",[trim(strtolower($data['brand']))])
        // whereRaw('LOWER(JSON_EXTRACT(name, "$.ru")) like ?', ['"' . trim(strtolower($data['brand'])) . '"'])
          ->first();
      
      if($brand) {
        $product->brand_id = $brand->id;
        return;
      }

      // Checking the brand in the list of correspondences
      $bs = $this->currentSource->bs()
                ->whereRaw('LOWER(`name`) LIKE ? ',[trim(strtolower($data['brand'])).'%'])
                ->whereNotNull('brand_id')
                ->first();

      if($bs) {
        $product->brand_id = $bs->brand_id;
        return;
      }
      
      
      if($this->settings['createNewBrand'] !== "1") {
        return;
      }

      // Else create new brand
      $brand = new Brand;
      $brand->setTranslation('name', $this->lang, $data['brand']);
      $brand->slug = SlugService::createSlug(Brand::class, 'slug', $data['brand']);
      $brand->save();
            
      $product->brand_id = $brand->id;
    }
    

    /**
     * setProductCategory
     *
     * @param  mixed $product
     * @param  mixed $data
     * @return void
     */
    private function attachProductCategory(&$product, $data) {

      $cs = $this->currentSource->cs()
                    ->whereRaw('LOWER(`name`) LIKE ? ',[trim(strtolower($data['category'])).'%'])
                    ->whereNotNull('category_id')
                    ->first();

      if(!$cs) {
        return;
      }

      $product->categories()->attach($cs->category_id);
    }
        
      
    /**
     * setProductData
     *
     * @param  mixed $sp
     * @param  mixed $data
     * @return void
     */
    private function setProductData(&$product, $data) {
      $product->price = $this->getPrice($data);
      $product->in_stock = $this->getInStock($data);
      // $product->barcode = $data['barcode'];
      $product->code = $data['code'];
    }

    /**
     * setSupplierData
     *
     * @param  mixed $sp
     * @param  mixed $data
     * @return void
     */
    private function setSupplierData(&$sp, $data) {
      $sp->supplier_id = $this->currentSource->supplier_id;

      $sp->code = $data['code'] ?? null;
      $sp->barcode = $data['barcode'] ?? null;
      $sp->price = $this->getPrice($data);
      $sp->in_stock = $this->getInStock($data);
    }


    /**
     * setProductInitFields
     *
     * @param  mixed $product
     * @return void
     */
    private function setProductInitFields(&$product) {
      $product->is_active = 0;
    }
    
    /**
     * setProductName
     *
     * @param  mixed $product
     * @param  mixed $data
     * @return void
     */
    private function setProductName(&$product, array $data) {
      $product->setTranslation('name', $this->lang, $data['name']);
      $product->slug = SlugService::createSlug($this->PRODUCT_CLASS, 'slug', $data['name']);
    }

    
    /**
     * The function `setProductImage` processes an array of image links and stores them in a JSON
     * format within the `images` property of a product object.
     * 
     * @param product  is an object representing a product. It likely has properties such as
     * name, price, description, and images. The setProductImage function is used to set the images
     * property of the product object based on the data provided.
     * @param data The `setProductImage` function takes two parameters: `` and ``. The
     * `` parameter is an array that contains information about the images of the product. The
     * function checks if the 'images' key in the `` array is empty. If it is not empty,
     * 
     * @return If the `['images']` array is empty, the function will return without making any
     * changes to the product.
     */
    private function setProductImage(&$product, array $data) {
      if(empty($data['images'])) {
        return;
      }

      $links_array = (array)$data['images'];
      $images = [];


      foreach($links_array as $index => $link) {
        if(!empty($link) && $this->ifImageIndexAllowed($index)) {
          $images[] = [
            'src' => $link,
            'alt' => null,
            'title' => null,
          ];
        }
      }

      if(!empty($images)) {
        $product->images = $images;
      }
    }

    /**
     * The function `ifImageIndexAllowed` checks if a given index is allowed based on a list of allowed
     * indexes stored in the settings.
     * 
     * @param index The `ifImageIndexAllowed` function checks if a given index is allowed based on the
     * settings provided. It first checks if the `imageIndexes` setting is set in the `->settings`
     * array. If it is set, it converts the string of indexes into an array of integers and then
     * 
     * @return The function `ifImageIndexAllowed` returns `true` if the provided `` is found in
     * the array of image indexes specified in the settings, and `false` otherwise. If the
     * `imageIndexes` setting is not set or empty, the function will return nothing.
     */
    private function ifImageIndexAllowed($index) {
      // Check if imageIndexes is set
      if(!empty($this->settings['imageIndexes'])) {
        $indexes_string = $this->settings['imageIndexes'];
      }else {
        return true;
      }

      // Convert string to array
      $indexes_array = explode(',', $indexes_string);
      
      // Convert string array to int array 
      $indexes_array_num = array_map(function($item) {
        return (int)$item;
      }, $indexes_array);


      $index_num = (int)$index + 1;

      // Check if index is in array
      if(in_array($index_num, $indexes_array_num)) {
        return true;
      }else {
        return false;
      }
    }

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

        
    /**
     * bootSource
     *
     * @param  mixed $source
     * @return void
     */
    private function bootSource($source) {
      // Set current source in processing
      $this->currentSource = $source;

      // Create upload history
      $this->createUploadHistory();

      // Get Exchange rates
      $this->exchange_rate = $this->getRate();

      // Fill Settings
      $this->settings = $source->settings;

      // Clear force update
      $source->clearForceUpdateAndSave();

      // Fill Letters
      if($source->type === 'file') {
        $this->setFieldLetter();
      }

      $this->lang = $this->settings['language'] ?? 'en';
      $this->available_languages = config('backpack.crud.locales');

      // Fill rules
      $rules = [];
      foreach($source->rules as $rule) {
  
        // Transform JSON lists to simple arrays
        $rule['brands'] = $this->simplify_values($rule['brands']);
        $rule['codes'] = $this->simplify_values($rule['codes']);
        $rule['names'] = $this->simplify_values($rule['names']);

        // Create empty array for this rule type
        if(!isset($rules[$rule['type']]) || empty($rules[$rule['type']])) {
          $rules[$rule['type']] = [];
        }

        // Sorting one type rules because: 
        // 1) at first common rules should be applied
        // 2) at last more spicific rules should be applied
        if($rule['target'] === 'all') {
          array_unshift($rules[$rule['type']], $rule);
        }else {
          $rules[$rule['type']][] = $rule;
        }
      }

      $this->rules = $rules;

      // Fill inStock rules
      $this->stockRules = [];

      if(isset($this->settings['inStockRules']) && !empty($this->settings['inStockRules'])) {
        $stock_rules_array = json_decode($this->settings['inStockRules'], true);
  
        foreach($stock_rules_array as $rule) {
          $this->stockRules[$rule['key']] = $rule;
        }
      }

      // Fill categories
      if($source->cs->count()) {
        $this->cs = $source->cs;
      }
    }
        
    /**
     * Method getFieldLetter
     *
     * @param $field $field [explicite description]
     *
     * @return void
     */
    private function getFieldLetter($field) {
      return isset($this->fieldLetters[$field]) && !empty($this->fieldLetters[$field])? $this->fieldLetters[$field]: null;
    }
        
    /**
     * Method setFieldLetter
     *
     * @return void
     */
    private function setFieldLetter() {
      $this->fieldLetters = [
        'name' => null,
        'category' => null,
        'brand' => null,
        'inStock' => null,
        'code' => null,
        'barcode' => null,
        'price' => null,
        'image' => null
      ];

      $this->fieldLetters['category'] = ($category = trim($this->settings['categories_column_letter'] ?? '')) === '' ? null : mb_trim(mb_strtoupper($category));
      $this->fieldLetters['brand'] = ($brand = trim($this->settings['brands_column_letter'] ?? '')) === '' ? null : mb_trim(mb_strtoupper($brand));

      $this->fieldLetters['name'] = ($name = trim($this->settings['fieldName'] ?? '')) === '' ? null : mb_trim(mb_strtoupper($name));
      $this->fieldLetters['inStock'] = ($inStock = trim($this->settings['fieldInStock'] ?? '')) === '' ? null : mb_trim(mb_strtoupper($inStock));
      $this->fieldLetters['code'] = ($code = trim($this->settings['fieldCode'] ?? '')) === '' ? null : mb_trim(mb_strtoupper($code));
      $this->fieldLetters['barcode'] = ($barcode = trim($this->settings['fieldBarcode'] ?? '')) === '' ? null : mb_trim(mb_strtoupper($barcode));
      $this->fieldLetters['price'] = ($price = trim($this->settings['fieldPrice'] ?? '')) === '' ? null : mb_trim(mb_strtoupper($price));
      $this->fieldLetters['image'] = ($image = trim($this->settings['fieldImage'] ?? '')) === '' ? null : mb_trim(mb_strtoupper($image));
    }
    
    /**
     * simplify_values
     *
     * @param  mixed $values
     * @return void
     */
    private function simplify_values($values = null) {
      if(empty($values)) {
        return null;
      }

      $arr = json_decode($values, true);
      $arr_simple = Arr::flatten($arr);
      return $arr_simple;
    }
    
    /**
     * isRuleForProduct
     *
     * @param  mixed $rule
     * @param  mixed $product
     * @return void
     */
    private function isRuleForProduct($rule, $product){
      if($rule['target'] === 'all') {
        return true;
      }

      // IF BANNED BY BRANDS LIST
      if($rule['target'] === 'brand' && !empty($rule['brands']) && is_array($rule['brands'])) {
        if($this->searchInArray($product['brand'], $rule['brands'])) {
          return true;
        }
      }

      // IF BANNED BY NAMES LIST
      if($rule['target'] === 'name' && !empty($rule['names']) && is_array($rule['names'])) {
        if($this->searchInArray($product['name'], $rule['names'])) {
          return true;
        }
      }

      // IF BANNED BY CODES LIST
      if($rule['target'] === 'code' && !empty($rule['codes']) && is_array($rule['codes'])) {
        if($this->searchInArray($product['code'], $rule['codes'], true)) {
          return true;
        }
      }

      // IF BANNED BY PRICE
      if($rule['target'] === 'price' && (!empty($rule['min_price']) || !empty($rule['max_price']))) {
        $points = 0;

        if($rule['min_price'] !== null && $product['price'] >= $rule['min_price']) {
          $points += 1;
        }

        if($rule['max_price'] !== null && $product['price'] <= $rule['max_price']) {
          $points += 1;
        }

        if($points === 2) {
          return 2;
        }
      }

      return false;
    }

        
    /**
     * searchInArray
     *
     * @param  mixed $search
     * @param  mixed $array
     * @return void
     */
    private function searchInArray($search, $array) {
      if(!is_array($array) || empty($array) || !is_string($search)) {
        return false;
      }

      $search = trim($search);

      for($i = 0; $i < count($array); $i++) {
        $item = trim($array[$i]);
        
        // Try to find %% rule
        preg_match('/^%(.+)%$/i', $item, $matches, PREG_UNMATCHED_AS_NULL);
        if(!empty($matches[1])) {
          $search_anywhere = strpos($search, $matches[1]);

          // if false continue to search
          if($search_anywhere !== false) {
            return true;
          }
        }
        
        $matches = null;
        // Try find starts with rule
        preg_match('/^\^(.+)/i', $item, $matches, PREG_UNMATCHED_AS_NULL);
        if(!empty($matches[1])) {
          $search_starts_with = str_starts_with($search, $matches[1]);

          // if false continue to search
          if($search_starts_with) {
            return true;
          }
        }

        $matches = null;
        // Search exactly
        preg_match('/^' . $item. '$/i', $search, $matches, PREG_UNMATCHED_AS_NULL);
        if(!empty($matches)) {
          return true;
        }
      }

      return false;
    }
    
    /**
     * getXMLCatalog
     *
     * @return void
     */
    private function getXMLCatalog($url) {
	    try 
	    {
	    	$xml = simplexml_load_file($url);
	    }
	    catch(\Exception $e)
	    {  
		    $message = "Can't get products catalog: " . $e->getMessage();
		    
				\Log::channel('xml')->error($message);
				throw new \Exception($message);
			}
	    
      return $xml;
    }
    
    /**
     * validateData
     *
     * @param  mixed $data
     * @return void
     */
    private function validateData($data) {
      // SKIP PRODUCTS THAT ARTICUL STARTS WITH sale_
      // if(str_starts_with($xml_product['articul'], 'sale_'))
      //   continue;

      // ACCEPT ONLY IF IN WHITELIST
      if(isset($this->rules['whitelist']) && !empty($this->rules['whitelist'])) {
        foreach($this->rules['whitelist'] as $whitelist){
          if($this->isRuleForProduct($whitelist, $data)) {
            return true;
          }
        }
        
        // If product is not in whitelists skip all next checks and return false
        return false;
      }
    
      // SKIP IF IN BLACKLIST
      if(isset($this->rules['blacklist']) && !empty($this->rules['blacklist'])) {
        foreach($this->rules['blacklist'] as $blacklist){
          if($this->isRuleForProduct($blacklist, $data)) {
            return false;
          }
        }

        return true;
      }

      return true;
    }
}
