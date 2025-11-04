<?php

namespace Backpack\Store\app\DTO;

class ShippingQuoteRequest
{
    public string $methodKey;          // 'novaposhta_warehouse', 'packeta_address', ...
    public string $destinationCountry; // ISO2 страны назначения ('UA', 'CZ', 'DE', 'ES', ...)
    public int    $weightG;            // фактический вес, грамм
    public ?int   $lengthCm;           // L, см
    public ?int   $widthCm;            // W, см
    public ?int   $heightCm;           // H, см

    public bool   $codEnabled;         // true, если заказ с наложенным платежом
    public float  $codAmount;          // сумма COD в валюте провайдера (или объявления)
    public ?string $lockerSize;        // для НП почтомат: 'S'|'M'|'L' (если применимо)
    public ?float  $declaredValue;     // объявленная стоимость для страховки (валюта провайдера)

    /** Произвольные метаданные (если надо передать что-то ещё). */
    public array $meta = [];

    public function __construct(array $data)
    {
        $this->methodKey          = (string) ($data['methodKey'] ?? '');
        $this->destinationCountry = strtoupper((string) ($data['destinationCountry'] ?? ''));
        $this->weightG            = (int) ($data['weightG'] ?? 0);
        $this->lengthCm           = isset($data['lengthCm']) ? (int) $data['lengthCm'] : null;
        $this->widthCm            = isset($data['widthCm'])  ? (int) $data['widthCm']  : null;
        $this->heightCm           = isset($data['heightCm']) ? (int) $data['heightCm'] : null;
        $this->codEnabled         = (bool) ($data['codEnabled'] ?? false);
        $this->codAmount          = (float) ($data['codAmount'] ?? 0.0);
        $this->lockerSize         = $data['lockerSize'] ?? null;
        $this->declaredValue      = isset($data['declaredValue']) ? (float) $data['declaredValue'] : null;
        $this->meta               = (array) ($data['meta'] ?? []);
    }
}
