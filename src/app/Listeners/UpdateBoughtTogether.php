<?php

namespace Backpack\Store\app\Listeners;

use Backpack\Store\app\Models\BoughtTogether;

class UpdateBoughtTogether
{
    public function handle($event): void
    {
        // $event должен содержать order с products и country_code
        $items = collect($event->order->products ?? [])->pluck('id')->unique()->values()->all();
        $country = $event->order->country_code ?? null;

        // инкремент пар
        foreach ($items as $i) {
            foreach ($items as $j) {
                if ($i === $j) continue;
                BoughtTogether::query()->updateOrCreate(
                    ['product_id'=>$i,'with_product_id'=>$j,'country_code'=>$country],
                    []
                )->increment('score');
            }
        }
    }
}
