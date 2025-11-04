<?php

namespace Backpack\Store\app\Settings;

use Backpack\Settings\Contracts\SettingsRegistrarInterface;
use Backpack\Settings\Services\Registry\Field;
use Backpack\Settings\Services\Registry\Registry;
use Illuminate\Support\Arr;

class InvoicesSettingsRegistrar implements SettingsRegistrarInterface
{
    public function register(Registry $registry): void
    {
        $registry->group('store-invoices', function ($group) {
            $group->title('Счета и PDF')->icon('la la-file-invoice')
                ->page('Основное', function ($page) {
                    $templates = collect(config('dress.invoice.templates', []))
                        ->mapWithKeys(fn ($template, $key) => [$key => Arr::get($template, 'name', $key)])
                        ->all();

                    $page->add(Field::make('store.invoices.default_template', 'select_from_array')
                        ->label('Шаблон по умолчанию')
                        ->options($templates)
                        ->default(config('dress.invoice.default_template'))
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.locale', 'text')
                        ->label('Локаль форматирования')
                        ->default(config('dress.invoice.locale'))
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.currency', 'text')
                        ->label('Валюта по умолчанию')
                        ->default(config('dress.invoice.currency'))
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.numbering.pattern', 'text')
                        ->label('Шаблон номера счета')
                        ->default(config('dress.invoice.numbering.pattern'))
                        ->hint('{Y} - год, {m} - месяц, {order_id}, {order_code}')
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.numbering.due_days', 'number')
                        ->label('Срок оплаты (дней)')
                        ->default((int) config('dress.invoice.numbering.due_days', 14))
                        ->cast('int')
                    );

                    $page->add(Field::make('store.invoices.numbering.tax_date_offset', 'number')
                        ->label('Смещение даты нал. события (дней)')
                        ->default((int) config('dress.invoice.numbering.tax_date_offset', 0))
                        ->cast('int')
                    );
                })
                ->page('Реквизиты продавца', function ($page) {
                    $page->add(Field::make('store.invoices.seller.name', 'text')
                        ->label('Название компании')
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.seller.ico', 'text')
                        ->label('IČO')
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.seller.dic', 'text')
                        ->label('DIČ')
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.seller.vat_number', 'text')
                        ->label('VAT номер')
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.seller.address.street', 'text')
                        ->label('Улица и дом')
                        ->cast('string')
                    );
                    $page->add(Field::make('store.invoices.seller.address.city', 'text')
                        ->label('Город')
                        ->cast('string')
                    );
                    $page->add(Field::make('store.invoices.seller.address.zip', 'text')
                        ->label('Индекс')
                        ->cast('string')
                    );
                    $page->add(Field::make('store.invoices.seller.address.country', 'text')
                        ->label('Страна')
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.seller.contacts.email', 'text')
                        ->label('E-mail')
                        ->cast('string')
                    );
                    $page->add(Field::make('store.invoices.seller.contacts.phone', 'text')
                        ->label('Телефон')
                        ->cast('string')
                    );
                    $page->add(Field::make('store.invoices.seller.contacts.website', 'text')
                        ->label('Сайт')
                        ->cast('string')
                    );
                })
                ->page('Банк и QR', function ($page) {
                    $page->add(Field::make('store.invoices.bank_accounts', 'repeatable')
                        ->label('Банковские реквизиты по странам')
                        ->fields([
                            [
                                'name' => 'country',
                                'label' => 'Страна (ISO)',
                                'type' => 'text',
                            ],
                            [
                                'name' => 'iban',
                                'label' => 'IBAN',
                                'type' => 'text',
                            ],
                            [
                                'name' => 'bic',
                                'label' => 'BIC/SWIFT',
                                'type' => 'text',
                            ],
                            [
                                'name' => 'account_display',
                                'label' => 'Номер счета (отображение)',
                                'type' => 'text',
                            ],
                            [
                                'name' => 'bank_name',
                                'label' => 'Банк',
                                'type' => 'text',
                            ],
                        ])
                        ->hint('Добавьте IBAN для каждой страны. Страны будут сопоставляться с кодом заказа.')
                        ->cast('array')
                    );

                    $page->add(Field::make('store.invoices.qr.message_pattern', 'text')
                        ->label('Сообщение в QR')
                        ->hint('Доступны плейсхолдеры {invoice_number}, {order_number}')
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.qr.cache_disk', 'text')
                        ->label('Диск для QR')
                        ->default(config('dress.invoice.qr.cache_disk', 'public'))
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.qr.cache_ttl', 'number')
                        ->label('TTL кэша QR (сек.)')
                        ->default((int) config('dress.invoice.qr.cache_ttl', 604800))
                        ->cast('int')
                    );
                })
                ->page('Файлы и ссылки', function ($page) {
                    $page->add(Field::make('store.invoices.storage.disk', 'text')
                        ->label('Диск для PDF')
                        ->default(config('dress.invoice.storage.disk', 'public'))
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.storage.path_mask', 'text')
                        ->label('Маска пути для PDF')
                        ->default(config('dress.invoice.storage.path_mask'))
                        ->hint('{Y}/{m}/{invoice_number}.pdf и т.п.')
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.signed_url.ttl', 'text')
                        ->label('TTL подписанных ссылок (ISO8601 или секунды)')
                        ->default(config('dress.invoice.signed_url.ttl', 'P7D'))
                        ->cast('string')
                    );

                    $page->add(Field::make('store.invoices.assets.logo_url', 'text')
                        ->label('URL логотипа')
                        ->cast('string')
                    );
                    $page->add(Field::make('store.invoices.assets.stamp_url', 'text')
                        ->label('URL печати')
                        ->cast('string')
                    );
                    $page->add(Field::make('store.invoices.assets.signature_url', 'text')
                        ->label('URL подписи')
                        ->cast('string')
                    );
                });
        });
    }
}
