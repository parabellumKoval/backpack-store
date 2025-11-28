<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class SearchQuery extends Model
{
    use CrudTrait;
    use FormatsUniqAttribute;

    protected $table = 'ak_search_queries';
    protected $guarded = ['id'];
    public $timestamps = true;

    public function getHasResultsAttribute(): bool
    {
        return (int)$this->results_count > 0;
    }


    public function getSettingsButtonHtml()
    {
      return '<a href="'.url('admin/settings/search').'" class="btn btn-outline-dark">
                            <i class="la la-gear"></i> Настройки поиска
                        </a>';
    }

    public function getUniqStringAttribute(): string
    {
        return $this->formatUniqString([
            '#'.$this->id,
            $this->q,
            $this->country_code,
            $this->locale,
            sprintf('results: %s', $this->results_count ?? 0),
            $this->took_ms !== null ? sprintf('took %sms', $this->took_ms) : null,
            $this->created_at ? $this->created_at->format('Y-m-d H:i') : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->q,
        ]);

        return $this->formatUniqHtml($headline, [
            $this->country_code,
            $this->locale,
            sprintf('results: %s', $this->results_count ?? 0),
            $this->took_ms !== null ? sprintf('took %sms', $this->took_ms) : null,
            $this->created_at ? $this->created_at->format('Y-m-d H:i') : null,
        ]);
    }
}
