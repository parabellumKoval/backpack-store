<?php

namespace Backpack\Store\app\Services\Payments;

use Backpack\Store\app\Contracts\Payments\OnlinePaymentProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PaymentProviderRegistry
{
    protected array $providers = [];

    public function __construct(protected Container $container)
    {
    }

    public function register(string|int $key, string|OnlinePaymentProvider|array|null $provider = null): void
    {
        if (is_array($provider)) {
            $provider = $provider['class'] ?? null;
        }

        if ($provider === null) {
            $provider = $key;
            $instance = $this->instantiate($provider);
            $key = $instance->key();
        } elseif (is_int($key)) {
            $instance = $this->instantiate($provider);
            $key = $instance->key();
        }

        $key = trim((string) $key);

        if ($key === '') {
            throw new InvalidArgumentException('Payment provider key can not be empty.');
        }

        $this->providers[$key] = $provider;
    }

    public function provider(string $key): OnlinePaymentProvider
    {
        $key = trim($key);

        if ($key === '' || !isset($this->providers[$key])) {
            throw new InvalidArgumentException("Payment provider [{$key}] is not registered.");
        }

        return $this->instantiate($this->providers[$key]);
    }

    public function all(): array
    {
        return $this->providers;
    }

    protected function instantiate(string|OnlinePaymentProvider|array $provider): OnlinePaymentProvider
    {
        if (is_array($provider)) {
            $provider = $provider['class'] ?? null;
        }

        if ($provider instanceof OnlinePaymentProvider) {
            return $provider;
        }

        if (!is_string($provider) || $provider === '') {
            throw new InvalidArgumentException('Payment provider class is not configured.');
        }

        $instance = $this->container->make($provider);

        if (!$instance instanceof OnlinePaymentProvider) {
            throw new InvalidArgumentException("Payment provider [{$provider}] must implement " . OnlinePaymentProvider::class . '.');
        }

        return $instance;
    }
}
