<?php

namespace Backpack\Store\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;
use Backpack\Helpers\Traits\FormatsUniqAttribute;
use Backpack\Store\app\Models\Traits\HasFaqData;
use Illuminate\Database\Eloquent\Model;

class FaqTemplate extends Model
{
    use CrudTrait;
    use HasTranslations;
    use FormatsUniqAttribute;
    use HasFaqData;

    protected $table = 'ak_faq_templates';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $fakeColumns = ['extras_trans'];

    protected $translatable = ['name', 'extras_trans'];

    public function getUniqStringAttribute(): string
    {
        return $this->formatUniqString([
            '#'.$this->id,
            $this->name,
            sprintf('status: %s', $this->is_active ? 'active' : 'hidden'),
            sprintf('faq items: %d', $this->getAdminFaqItemsCount()),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $headline = $this->formatUniqString([$this->name ?: ('#'.$this->id)]);

        return $this->formatUniqHtml($headline, [
            '#'.$this->id,
            sprintf('status: %s', $this->is_active ? 'active' : 'hidden'),
            sprintf('faq items: %d', $this->getAdminFaqItemsCount()),
        ]);
    }

    public function getAdminFaqItemsCount(): int
    {
        return count($this->getFaqItems());
    }
}
