<?php

namespace Backpack\Store\app\Http\Resources;

class OrderLargeResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      return [
        'id' => $this->id,
        'code' => $this->code,
        'price' => $this->price,
        'subtotal' => $this->subtotal,
        'discountTotal' => $this->discount_total,
        'promocodeDiscountTotal' => $this->promocode_discount_total,
        'bonusDiscountTotal' => $this->bonus_discount_total,
        'personalDiscountTotal' => $this->personal_discount_total,
        'shippingTotal' => $this->shipping_total,
        'taxTotal' => $this->tax_total,
        'grandTotal' => $this->grand_total,
        'currencyCode' => $this->currency_code,
        'status' => $this->status,
        'payStatus' => $this->pay_status,
        'deliveryStatus' => $this->delivery_status,
  //'orderable' => $this->orderable,
        'user' => $this->user,
        'delivery' => $this->delivery,
        'payment' => $this->payment,
        'invoiceDownloadUrl' => $this->invoiceDownloadUrl,
        'invoiceQrUrl' => $this->invoiceQrUrl,
        'products' => $this->productsAnyway,
        'bonuses' => $this->bonusSummary(),
        'personalDiscount' => $this->personalDiscountSummary(),
        'created_at' => $this->created_at,
      ];
    }

    protected function bonusSummary(): array
    {
      $info = $this->info ?? [];
      $bonuses = $info['bonuses'] ?? [];

      $fiat = (float)($bonuses['fiat_amount'] ?? ($info['bonusesUsed'] ?? 0));

      return [
        'points' => (float)($bonuses['points'] ?? 0),
        'fiatAmount' => $fiat,
        'fiatCurrency' => $bonuses['fiat_currency'] ?? $this->currency_code,
        'walletCurrency' => $bonuses['wallet_currency'] ?? null,
        'walletCurrencyLabel' => isset($bonuses['wallet_currency'])
          ? store_currency_label($bonuses['wallet_currency'])
          : null,
        'refunded' => (bool)($bonuses['refunded'] ?? false),
      ];
    }

    protected function personalDiscountSummary(): array
    {
      $info = $this->info ?? [];
      $discount = $info['personalDiscount'] ?? [];

      return [
        'amount' => (float)($discount['amount'] ?? 0),
        'percent' => (float)($discount['percent'] ?? 0),
        'currency' => $discount['currency'] ?? $this->currency_code,
        'applied' => (bool)($discount['applied'] ?? false),
      ];
    }
}
