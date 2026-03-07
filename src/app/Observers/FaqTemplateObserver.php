<?php

namespace Backpack\Store\app\Observers;

use Backpack\Store\app\Models\FaqTemplate;
use Backpack\Store\app\Services\Faq\FaqTemplateCatalogTouchService;

class FaqTemplateObserver
{
    public function __construct(
        protected FaqTemplateCatalogTouchService $touchService
    ) {
    }

    public function saved(FaqTemplate $template): void
    {
        $this->touchService->touchByTemplateId((int) $template->id);
    }

    public function deleted(FaqTemplate $template): void
    {
        $this->touchService->touchByTemplateId((int) $template->id);
    }
}

