<?php

namespace Backpack\Store\app\Services\ProductLists\Supports;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AnchorResolver
{
    /**
     * @var array<string, class-string>
     */
    protected array $modelMap;

    public function __construct(?array $modelMap = null)
    {
        $config = $modelMap ?? config('dress.product_lists.anchor_models', []);
        $this->modelMap = $this->filterSupportedModels($config);
    }

    public function supportedAliases(): array
    {
        return array_keys($this->modelMap);
    }

    public function resolve(?string $alias, mixed $rawIds): ?AnchorSelection
    {
        if ($alias === null && ($rawIds === null || $rawIds === [])) {
            return null;
        }

        if ($alias === null) {
            throw new BadRequestHttpException('anchors[model] is required when anchors[ids] are provided.');
        }

        if (!isset($this->modelMap[$alias])) {
            $supported = $this->supportedAliases();
            $supportedList = $supported ? implode(', ', $supported) : 'none';
            throw new BadRequestHttpException(sprintf(
                'Unsupported anchors model "%s". Supported models: %s.',
                $alias,
                $supportedList
            ));
        }

        $ids = $this->normalizeIds($rawIds);
        if (empty($ids)) {
            throw new BadRequestHttpException('anchors[ids] must contain at least one integer identifier.');
        }

        return new AnchorSelection($alias, $this->modelMap[$alias], $ids);
    }

    protected function normalizeIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_scalar($raw)) {
            $raw = [$raw];
        }

        if (!is_array($raw)) {
            throw new BadRequestHttpException('anchors[ids] must be an array of identifiers.');
        }

        $ids = [];
        foreach ($raw as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (!is_numeric($value)) {
                throw new BadRequestHttpException('anchors[ids] must contain only integer values.');
            }
            $ids[] = (int) $value;
        }

        return array_values(array_unique($ids));
    }

    protected function filterSupportedModels(array $map): array
    {
        $supported = [];
        foreach ($map as $alias => $class) {
            if (!is_string($alias) || $alias === '') {
                continue;
            }
            if (!is_string($class) || $class === '' || !class_exists($class)) {
                continue;
            }
            $supported[$alias] = $class;
        }

        if (!isset($supported['product'])) {
            $supported['product'] = \Backpack\Store\app\Models\Product::class;
        }

        return $supported;
    }
}
