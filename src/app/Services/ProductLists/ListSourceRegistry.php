<?php

namespace Backpack\Store\app\Services\ProductLists;

use Backpack\Store\app\Services\ProductLists\Contracts\SourceResolver;
use InvalidArgumentException;

class ListSourceRegistry
{
    /** @var array<string, class-string<SourceResolver>> */
    protected array $map = [];
    /** @var array<string, SourceResolver> */
    protected array $resolved = [];

    public function register(string $alias, string $class): void
    {
        $this->map[$alias] = $class;
    }

    public function get(string $alias): ?SourceResolver
    {
        $class = $this->map[$alias] ?? null;
        if (!$class) {
            return null;
        }

        if (!isset($this->resolved[$alias])) {
            $resolver = app($class);
            if (!$resolver instanceof SourceResolver) {
                throw new InvalidArgumentException(sprintf(
                    'Resolver for alias "%s" must implement %s.',
                    $alias,
                    SourceResolver::class
                ));
            }

            if (!$resolver->supports($alias)) {
                throw new InvalidArgumentException(sprintf(
                    'Resolver %s does not support alias "%s".',
                    $class,
                    $alias
                ));
            }

            $this->resolved[$alias] = $resolver;
        }

        return $this->resolved[$alias];
    }

    public function all(): array { return $this->map; }
}
