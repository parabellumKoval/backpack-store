<?php

namespace Backpack\Store\app\Console\Commands\Traits\XmlSource;

trait XmlSourceTrait {

  /**
   * Разбирает имя тега на префикс пространства имён и локальное имя.
   * "g:price" => ['g', 'price'], "price" => [null, 'price']
   *
   * @param  string $tag
   * @return array
   */
  private function splitNamespacedTag(string $tag): array
  {
      $position = strpos($tag, ':');

      if ($position === false) {
          return [null, $tag];
      }

      return [substr($tag, 0, $position), substr($tag, $position + 1)];
  }

  /**
   * Возвращает список дочерних узлов по имени тега с поддержкой пространств имён.
   * SimpleXML не умеет $node->{'g:price'}, поэтому для префиксных тегов
   * переключаем контекст через children($namespaceUri).
   *
   * @param  \SimpleXMLElement $ctx
   * @param  string $tag
   * @return \SimpleXMLElement|null
   */
  private function getNamespacedNodes(\SimpleXMLElement $ctx, string $tag)
  {
      [$prefix, $localName] = $this->splitNamespacedTag($tag);

      if ($prefix === null) {
          return isset($ctx->{$localName})? $ctx->{$localName}: null;
      }

      $namespaces = $ctx->getDocNamespaces(true);

      if (!isset($namespaces[$prefix])) {
          return null;
      }

      $children = $ctx->children($namespaces[$prefix]);

      return isset($children->{$localName})? $children->{$localName}: null;
  }

  /**
   * Возвращает первый дочерний узел по имени тега (с поддержкой пространств имён).
   *
   * @param  \SimpleXMLElement $ctx
   * @param  string $tag
   * @return \SimpleXMLElement|null
   */
  private function getNamespacedNode(\SimpleXMLElement $ctx, string $tag)
  {
      $nodes = $this->getNamespacedNodes($ctx, $tag);

      return $nodes === null? null: $nodes[0];
  }

  private function getXmlNodeAttributeValue(\SimpleXMLElement $ctx, string $attributeName): ?string
  {
      $attributeName = trim($attributeName);
      if ($attributeName === '') {
          return null;
      }

      $attributes = $ctx->attributes();
      if (!$attributes || !isset($attributes[$attributeName])) {
          return null;
      }

      return trim((string)$attributes[$attributeName]);
  }

  private function getNodeValuesBySpec(\SimpleXMLElement $ctx, string $spec): array
  {
      if (!preg_match('/^([A-Za-z0-9_:\-]+)(?:\[(.+)\])?$/u', $spec, $m)) {
          return [];
      }
      $tag = $m[1];

      // Без условия: могут быть несколько одноимённых тегов
      if (empty($m[2])) {
          $nodes = $this->getNamespacedNodes($ctx, $tag);
          if ($nodes === null) return [];
          $out = [];
          foreach ($nodes as $node) {
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
              $next = $this->getNamespacedNode($node, $part);
              if ($next === null) return [];
              $node = $next;
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
        if (str_starts_with($spec, '@')) {
            return $this->getXmlNodeAttributeValue($ctx, mb_substr($spec, 1));
        }

        // tag[cond]?
        if (!preg_match('/^([A-Za-z0-9_:\-]+)(?:\[(.+)\])?$/u', $spec, $m)) {
            return null;
        }

        $tag = $m[1];

        // Без условия: как раньше
        if (empty($m[2])) {
            // Узел может быть отсутствующим
            $node = $this->getNamespacedNode($ctx, $tag);
            if ($node === null) {
                return null;
            }
            return trim((string)$node);
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
                $next = $this->getNamespacedNode($node, $tag);
                if ($next === null) {
                    return null;
                }
                $node = $next;
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

      if(str_contains($fieldName, '->') || str_contains($fieldName, '[') || str_starts_with($fieldName, '@')) {
        $resolvedValue = $this->resolveFieldPath($item, $fieldName);
        return $resolvedValue === null ? $default : $resolvedValue;
      }

      $node = $this->getNamespacedNode($item, $fieldName);

      if($node === null) {
        return $default;
      }

      return $node->__toString();
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

      if(str_contains($fieldName, '->') || str_contains($fieldName, '[') || str_starts_with($fieldName, '@')) {
        $resolvedValue = $this->resolveFieldPathAll($item, $fieldName);
        return empty($resolvedValue) ? null : $resolvedValue;
      }

      $nodes = $this->getNamespacedNodes($item, $fieldName);

      if($nodes === null) {
        return null;
      }

      // Приводим узлы к строкам: (array) на SimpleXMLElement даёт объекты, а не ссылки
      $links = [];

      foreach($nodes as $node) {
        $link = trim((string)$node);

        if($link !== '') {
          $links[] = $link;
        }
      }

      return empty($links) ? null : $links;
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

    /**
     * extractXmlCategoryMap
     *
     * @param  mixed $xml
     * @return array
     */
    private function extractXmlCategoryMap($xml) {
      $categoriesMap = [];

      if(!$xml instanceof \SimpleXMLElement) {
        return $categoriesMap;
      }

      $categories = $xml->xpath('//category[@id]');

      if($categories === false) {
        return $categoriesMap;
      }

      foreach($categories as $category) {
        $attributes = $category->attributes();
        $id = isset($attributes['id']) ? trim((string)$attributes['id']) : '';
        $name = trim((string)$category);

        if($id === '' || $name === '') {
          continue;
        }

        $categoriesMap[$id] = $name;
      }

      return $categoriesMap;
    }

    /**
     * resolveXmlCategoryValue
     *
     * @param  mixed $categoryValue
     * @param  array $categoriesMap
     * @return string|null
     */
    private function resolveXmlCategoryValue($categoryValue, array $categoriesMap) {
      if ($categoryValue === null) {
        return null;
      }

      $categoryValue = trim((string)$categoryValue);

      if ($categoryValue === '') {
        return $categoryValue;
      }

      return $categoriesMap[$categoryValue] ?? $categoryValue;
    }


    private function loadFromXml($source) {
        $this->bootSource($source);
        $xml = $this->getXMLCatalog($source->link);
        $categoriesMap = $this->extractXmlCategoryMap($xml);

        $item = array_reduce(explode('->', $this->settings['item']), function($model, $property) {
            return $model === null? null: $this->getNamespacedNodes($model, trim($property));
        }, $xml);

        if($item === null) {
            $message = "Can't find items node by path: " . $this->settings['item'];
            \Log::channel('xml')->error($message);
            throw new \Exception($message);
        }

        if($this->IS_TEST_MODE) {
            $this->totalRecords = $this->TEST_ITEMS < 0 ? count($item) : $this->TEST_ITEMS;
        } else {
            $this->totalRecords = count($item);
        }

        $this->totalUploadHistory($this->totalRecords);

        for($i = 0; $i < $this->totalRecords; $i++){
            $categoryValue = $this->getItemFieldValue($item[$i], 'fieldCategory', null);
            $xml_product = [
                'category' => $categoryValue,
                'category_name' => $this->resolveXmlCategoryValue($categoryValue, $categoriesMap),
                'name'     => $this->getItemFieldValue($item[$i], 'fieldName', null),
                'brand'    => $this->getItemFieldValue($item[$i], 'fieldBrand', null),
                'inStock'  => $this->getItemFieldValue($item[$i], 'fieldInStock', null),
                'code'     => $this->getItemFieldValue($item[$i], 'fieldCode', null),
                'barcode'  => $this->getItemFieldValue($item[$i], 'fieldBarcode', null),
                'price'    => $this->getItemFieldValue($item[$i], 'fieldPrice', null),
            ];

            if(isset($this->settings['fieldImage']) && !empty($this->settings['fieldImage'])) {
                $imgs = $this->getItemImagesValue($item[$i]);
                if($imgs !== null && $imgs !== '') {
                    $xml_product['images'] = $imgs;
                }
            }

            if($this->validateData($xml_product)) {
                try {
                    $response = $this->updateOrCreateItem($xml_product);
                    $this->updateUploadHistory($response);
                } catch(\Throwable $e) {
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
