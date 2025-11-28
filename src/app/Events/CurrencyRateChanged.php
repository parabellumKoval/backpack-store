<?php

namespace Backpack\Store\app\Events;

use Backpack\Store\app\Models\Admin\CurrencyRate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class CurrencyRateChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $rateId,
        public string $currencyCode,
        public ?Carbon $fetchedAt
    ) {
    }

    public static function for(CurrencyRate $rate): self
    {
        $code = data_get($rate, 'base_currency', 'UAH');
        return new self($rate->id, (string) $code, $rate->fetched_at);
    }
}
