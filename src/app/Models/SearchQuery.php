<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class SearchQuery extends Model
{
    use CrudTrait;

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
}
