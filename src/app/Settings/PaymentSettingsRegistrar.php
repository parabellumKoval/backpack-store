<?php

namespace Backpack\Store\app\Settings;

use Backpack\Settings\Contracts\SettingsRegistrarInterface;
use Backpack\Settings\Services\Registry\Registry;
use Backpack\Settings\Services\Registry\Field;

class PaymentSettingsRegistrar implements SettingsRegistrarInterface
{
    public function register(Registry $registry): void
    {

        $countryOptions = \Store::countryOptions();
        $registry->group('payment', function ($group) use ($countryOptions) {

            $group->title('Оплата')->icon('la la-store')

                // ────────────────────────────────────────────────
                // Вкладка: ОБЩЕЕ
                // ────────────────────────────────────────────────
                ->page('Общее', function ($page) {
                    $payments = \Settings::get('dress.payment.methods', []);

                    $result_name_type_label = array_reduce($payments, function ($carry, $item) {
                        $key = $item['name'] . '_' . $item['type'];
                        $carry[$key] = $item['label'];
                        return $carry;
                    }, []);

                    $page->add(Field::make("payment.methods", 'select2_from_array')
                        ->label('Способы оплаты')
                        ->options($result_name_type_label)
                        ->allows_multiple(true)
                        ->cast('array')
                        ->hint('Выберите способы оплаты доступные в стране.')
                        ->regionable(true)
                    );
                });
        });
    }
}
