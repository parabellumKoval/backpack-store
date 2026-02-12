<?php

namespace Backpack\Store\app\Console\Commands\Traits\XmlSource;

trait XmlSourceTrait {

  private function getNodeValuesBySpec(\SimpleXMLElement $ctx, string $spec): array
  {
      if (!preg_match('/^([A-Za-z0-9_:\-]+)(?:\[(.+)\])?$/u', $spec, $m)) {
          return [];
      }
      $tag = $m[1];

      // Без условия: могут быть несколько одноимённых тегов
      if (empty($m[2])) {
          if (!isset($ctx->{$tag})) return [];
          $out = [];
          foreach ($ctx->{$tag} as $node) {
              $out[] = trim((string)$node);
          }
          return $out;
      }

      // С условием attr=value
      $cond = $m[2];
      if (!preg_match('/^@?([A-Za-z0-9_:\-]+)\s*=\s*(.+)$/u', $cond, $cm)) {
          return [];
      }
      $attr  = $cm[1];
      $value = trim($cm[2], " \t\n\r\0\x0B'\"");
      $quoted = $this->quoteForXPath($value);
      $xpath  = $tag . '[@' . $attr . '=' . $quoted . ']';

      $found = $ctx->xpath($xpath) ?: [];
      $out = [];
      foreach ($found as $n) {
          $out[] = trim((string)$n);
      }
      return $out;
  }

  // Множественный резолвер для путей вида "pictures->picture"
  private function resolveFieldPathAll(\SimpleXMLElement $ctx, ?string $path): array
  {
      if ($path === null || trim($path) === '') return [];
      $parts = array_map('trim', explode('->', $path));
      $node  = $ctx;

      // спускаемся по промежуточным узлам (берём первый матч)
      for ($i = 0; $i < count($parts) - 1; $i++) {
          $part = $parts[$i];
          if ($part === '') return [];
          if (str_contains($part, '[')) {
              $cond  = preg_replace('/^[^\[]+\[|\]$/', '', $part);
              if (preg_match('/^@?([A-Za-z0-9_:\-]+)\s*=\s*(.+)$/u', $cond, $cm)) {
                  $attr   = $cm[1];
                  $v      = trim($cm[2], " \t\n\r\0\x0B'\"");
                  $quoted = $this->quoteForXPath($v);
                  $tag    = preg_replace('/\[(.+)\]/', '', $part);
                  $found  = $node->xpath($tag . '[@' . $attr . '=' . $quoted . ']');
                  if (!$found || !isset($found[0])) return [];
                  $node = $found[0];
              } else {
                  return [];
              }
          } else {
              if (!isset($node->{$part})) return [];
              $node = $node->{$part};
          }
      }

      // последний шаг — вернуть ВСЕ значения
      return $this->getNodeValuesBySpec($node, end($parts));
  }

    // --- NEW: безопасно квотим литералы для XPath
    private function quoteForXPath(string $value): string
    {
        // Если строка без одинарных кавычек — оборачиваем в одинарные
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }
        // Если без двойных — оборачиваем в двойные
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }
        // Иначе собираем concat('a',"b",'c',...)
        $parts = preg_split('/(\'|")/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        $chunks = [];
        foreach ($parts as $part) {
            if ($part === "'") { $chunks[] = '"\'"'; continue; }
            if ($part === '"') { $chunks[] = "'\"'"; continue; }
            if ($part === '')  { continue; }
            $chunks[] = "'" . $part . "'";
        }
        return 'concat(' . implode(',', $chunks) . ')';
    }

    // --- NEW: достаём значение узла по спецификатору (один шаг)
    // Примеры спецификатора: "name", "param[name=Артикул]", "price[currency=UAH]"
    private function getNodeValueBySpec(\SimpleXMLElement $ctx, string $spec): ?string
    {
        // tag[cond]?
        if (!preg_match('/^([A-Za-z0-9_:\-]+)(?:\[(.+)\])?$/u', $spec, $m)) {
            return null;
        }

        $tag = $m[1];

        // Без условия: как раньше
        if (empty($m[2])) {
            // Узел может быть отсутствующим
            if (!isset($ctx->{$tag})) {
                return null;
            }
            return trim((string)$ctx->{$tag});
        }

        // С условием вида attr=value (поддержка пробелов и кириллицы)
        // Можно расширить до нескольких условий через &/, при желании
        $cond = $m[2];

        // Допускаем формы: name=Артикул, @name=Артикул, name="Артикул"
        if (!preg_match('/^@?([A-Za-z0-9_:\-]+)\s*=\s*(.+)$/u', $cond, $cm)) {
            return null;
        }
        $attr  = $cm[1];
        $value = trim($cm[2]);
        // снимаем кавычки, если есть
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = mb_substr($value, 1, mb_strlen($value) - 2);
        }

        $quoted = $this->quoteForXPath($value);
        $xpath  = $tag . '[@' . $attr . '=' . $quoted . ']';

        $found = $ctx->xpath($xpath);
        if (!$found || !isset($found[0])) {
            return null;
        }
        return trim((string)$found[0]);
    }

    // --- NEW: поддержка путей вида "offer->param[name=Артикул]"
    private function resolveFieldPath(\SimpleXMLElement $ctx, ?string $path): ?string
    {
        // если путь не задан – ничего не достаём
        if ($path === null) {
            return null;
        }
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $parts = array_map('trim', explode('->', $path));
        $node  = $ctx;

        foreach ($parts as $i => $part) {
            if ($part === '') { // на всякий случай защищаемся от "->" подряд
                return null;
            }
            if ($i === count($parts) - 1) {
                return $this->getNodeValueBySpec($node, $part);
            }

            // спуск по промежуточной части пути
            if (!preg_match('/^([A-Za-z0-9_:\-]+)(?:\[.+\])?$/u', $part, $m)) {
                return null;
            }
            $tag = $m[1];

            if (str_contains($part, '[')) {
                // промежуточный селектор с условием
                $cond  = preg_replace('/^[^\[]+\[|\]$/', '', $part);
                if (preg_match('/^@?([A-Za-z0-9_:\-]+)\s*=\s*(.+)$/u', $cond, $cm)) {
                    $attr   = $cm[1];
                    $v      = trim($cm[2], " \t\n\r\0\x0B'\"");
                    $quoted = $this->quoteForXPath($v);
                    $tag    = preg_replace('/\[(.+)\]/', '', $part);
                    $found  = $node->xpath($tag . '[@' . $attr . '=' . $quoted . ']');
                    if (!$found || !isset($found[0])) return null;
                    $node = $found[0];
                    continue;
                }
                return null;
            } else {
                if (!isset($node->{$tag})) {
                    return null;
                }
                $node = $node->{$tag};
            }
        }
        return null;
    }

    /**
     * getItemFieldValue
     *
     * @param  mixed $item
     * @param  string $settingFieldName
     * @param  mixed $default
     * @return mixed
     */
    private function getItemFieldValue($item, $settingFieldName, $default = '') {
      $fieldName = $this->settings[$settingFieldName] ?? null;
      if(empty($fieldName)) {
        return $default;
      }

      if($this->isFieldInAttributes($settingFieldName)) {
        $attributeValue = $this->getItemAttributeValue($item, $fieldName);
        return $attributeValue === null ? $default : $attributeValue;
      }

      if(!isset($item->{$fieldName})) {
        return $default;
      }

      return $item->{$fieldName}->__toString();
    }

    /**
     * getItemImagesValue
     *
     * @param  mixed $item
     * @return mixed
     */
    private function getItemImagesValue($item) {
      $fieldName = $this->settings['fieldImage'] ?? null;
      if(empty($fieldName)) {
        return null;
      }

      if($this->isFieldInAttributes('fieldImage')) {
        return $this->getItemAttributeValue($item, $fieldName);
      }

      if(!isset($item->{$fieldName})) {
        return null;
      }

      return $item->{$fieldName};
    }

    /**
     * getItemAttributeValue
     *
     * @param  mixed $item
     * @param  string $attributeName
     * @return string|null
     */
    private function getItemAttributeValue($item, $attributeName) {
      $attributes = $item->attributes();
      if(!$attributes || !isset($attributes[$attributeName])) {
        return null;
      }

      return (string)$attributes[$attributeName];
    }


    private function loadFromXml($source) {
        $this->bootSource($source);
        $xml = $this->getXMLCatalog($source->link);

        $item = array_reduce(explode('->', $this->settings['item']), function($model, $property) {
            return $model->{$property};
        }, $xml);

        if($this->IS_TEST_MODE) {
            $this->totalRecords = $this->TEST_ITEMS === -1? count($item): $this->TEST_ITEMS;
        } else {
            $this->totalRecords = count($item);
        }

        $this->totalUploadHistory($this->totalRecords);

        for($i = 0; $i < $this->totalRecords; $i++){
            $xml_product = [
                'category' => $this->resolveFieldPath($item[$i], $this->settings['fieldCategory']) ?? null,
                'name'     => $this->resolveFieldPath($item[$i], $this->settings['fieldName']) ?? null,
                'brand'    => $this->resolveFieldPath($item[$i], $this->settings['fieldBrand']) ?? null,
                'inStock'  => $this->resolveFieldPath($item[$i], $this->settings['fieldInStock']) ?? null,
                'code'     => $this->resolveFieldPath($item[$i], $this->settings['fieldCode']) ?? null,
                'barcode'  => $this->resolveFieldPath($item[$i], $this->settings['fieldBarcode']) ?? null,
                'price'    => $this->resolveFieldPath($item[$i], $this->settings['fieldPrice']) ?? null,
            ];

            if(isset($this->settings['fieldImage']) && !empty($this->settings['fieldImage'])) {
                // если картинок много, вернуть массив строк через xpath
                $imgs = $this->resolveFieldPathAll($item[$i], $this->settings['fieldImage']);
                $xml_product['images'] = $imgs;
            }

            if($this->validateData($xml_product)) {
                try {
                    $response = $this->updateOrCreateItem($xml_product);
                    $this->updateUploadHistory($response);
                } catch(\Exception $e) {
                    $this->errorUploadHistory();
                    \Log::channel('xml')->error('updateOrCreateItem error: ' . $e->getMessage());
                    continue;
                }
            } else {
                $this->processedUploadHistory();
                continue;
            }
        }

        $this->setStatusUploadHistory('done');
    }

    private function getXMLCatalog($url) {
        try {
            $xml = simplexml_load_file($url);
        } catch(\Exception $e) {
            $message = "Can't get products catalog: " . $e->getMessage();
            \Log::channel('xml')->error($message);
            throw new \Exception($message);
        }
        return $xml;
    }
}
