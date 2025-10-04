<?php

namespace Backpack\Store\app\Services\ProductLists\Supports;

class SourceDefinition
{
    public readonly string $alias;
    public readonly ?string $group;
    public readonly array $params;
    public readonly array $filters;

    public function __construct(array $raw)
    {
        $alias = $raw['alias'] ?? null;
        if (!is_string($alias) || $alias === '') {
            throw new \InvalidArgumentException('Source definition requires a non-empty alias.');
        }
        $this->alias = $alias;
        $this->group = isset($raw['group']) && $raw['group'] !== '' ? (string)$raw['group'] : null;

        $params = $raw['params'] ?? [];
        if (!is_array($params)) {
            $params = [];
        }

        // flatten top-level params, params[] takes precedence
        $base = array_diff_key($raw, array_flip(['alias','group','filters','params']));
        $this->params = array_merge($base, $params);

        $filters = $raw['filters'] ?? [];
        $filters = is_string($filters) && !empty($filters)? json_decode($filters, true): $filters;
        $this->filters = is_array($filters) ? array_values($filters) : [];
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
