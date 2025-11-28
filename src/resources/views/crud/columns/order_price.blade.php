@php
  $style = isset($muted) && $muted ? 'opacity: 0.4;' : '';
  $order = $order ?? ($entry ?? null);
  $currencyLabel = $currency ?? ($order ? store_currency_label($order->currency_code ?? $order->currency ?? '') : '');

  $formatMoney = static function ($value) use ($currencyLabel) {
      if ($value === null) {
          return null;
      }

      $formatted = number_format((float) $value, 2, '.', ' ');

      return trim($formatted . ($currencyLabel ? ' ' . $currencyLabel : ''));
  };

  $info = (array) ($order->info ?? []);
  $bonusInfo = (array) ($info['bonuses'] ?? []);
  $bonusPoints = (float) ($bonusInfo['points'] ?? 0);
  $bonusPoints = $bonusPoints > 0 ? $bonusPoints : null;

  $bonusWalletLabel = null;
  if ($bonusPoints !== null) {
      $bonusWalletLabel = $bonusInfo['wallet_currency_label'] ?? null;
      if (!$bonusWalletLabel) {
          $walletCode = $bonusInfo['wallet_currency'] ?? null;
          $bonusWalletLabel = $walletCode ? store_currency_label($walletCode) : null;
      }
  }

  $promocodeDiscount = $order ? max(0, (float) ($order->promocode_discount_total ?? 0)) : 0;
  $personalDiscount = $order ? max(0, (float) ($order->personal_discount_total ?? 0)) : 0;

  $displayPrice = is_numeric($price ?? null)
      ? number_format((float) $price, 2, '.', ' ')
      : (string) $price;
@endphp

<div style="{{ $style }}">
  <div class="text-monospace" style="display: flex; align-items: center; gap: 6px;">
    <span style="font-weight: 600; font-size: 15px;">{{ $displayPrice }}</span>
    @if($currencyLabel)
      <span style="padding: 2px 6px; border-radius: 999px; background: #eef3ff; color: #1f3b73; font-size: 11px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;">
        {{ $currencyLabel }}
      </span>
    @endif
  </div>

  @if($bonusPoints !== null || $promocodeDiscount > 0 || $personalDiscount > 0)
    <div class="text-muted small" style="margin-top: 6px; display: flex; flex-direction: column; gap: 2px;">
      @if($bonusPoints !== null)
        <div style="display: flex; justify-content: space-between;">
          <span>🎁 Бонусы</span>
          <span>
            {{ number_format($bonusPoints, 2, '.', ' ') }}
            @if($bonusWalletLabel)
              <span>{{ $bonusWalletLabel }}</span>
            @endif
          </span>
        </div>
      @endif

      @if($promocodeDiscount > 0)
        <div style="display: flex; justify-content: space-between;">
          <span>🎟️ Промокод</span>
          <span>-{{ $formatMoney($promocodeDiscount) }}</span>
        </div>
      @endif

      @if($personalDiscount > 0)
        <div style="display: flex; justify-content: space-between;">
          <span>👤 Персональная</span>
          <span>-{{ $formatMoney($personalDiscount) }}</span>
        </div>
      @endif
    </div>
  @endif
</div>
