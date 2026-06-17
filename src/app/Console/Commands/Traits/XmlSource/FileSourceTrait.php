<?php

namespace Backpack\Store\app\Console\Commands\Traits\XmlSource;

use Illuminate\Support\Facades\Storage;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

trait FileSourceTrait {
  
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
        $this->totalRecords = $this->TEST_ITEMS < 0? $highestRow: $this->TEST_ITEMS;
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
          }catch(\Throwable $e) {
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
}
