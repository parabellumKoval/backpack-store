<?php

namespace Backpack\Store\app\Services\Invoice;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Arr;

class InvoiceRenderer
{
    public function render(string $html, array $templateConfig): string
    {
        $dompdf = $this->createInstance($templateConfig);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper(
            Arr::get($templateConfig, 'paper', 'A4'),
            Arr::get($templateConfig, 'orientation', 'portrait')
        );

        $dompdf->render();

        return $dompdf->output();
    }

    protected function createInstance(array $templateConfig): Dompdf
    {
        $options = new Options();
        $options->set('defaultFont', Arr::get($templateConfig, 'options.defaultFont', 'dejavusans'));
        $options->set('isRemoteEnabled', Arr::get($templateConfig, 'options.enable_remote', true));
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);

        if ($margins = Arr::get($templateConfig, 'margins')) {
            $dompdf->setPaper(
                Arr::get($templateConfig, 'paper', 'A4'),
                Arr::get($templateConfig, 'orientation', 'portrait')
            );
        }

        return $dompdf;
    }
}
