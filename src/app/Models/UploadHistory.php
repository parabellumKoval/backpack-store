<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;

// MODEL
use Backpack\Store\app\Models\Source;

class UploadHistory extends Model
{
    use HasFactory;
    use CrudTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_uploads';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];

    protected $casts = [
      'rules' => 'array'
    ];

    protected $fakeColumns = [];

    private $source_class = null;
    private $statuses = [
      'pending' => 'В обработке',
      'done' => 'Завершено',
      'error' => 'Ошибка'
    ];
    
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    
    /**
     * __constract
     *
     * @param  mixed $attributes
     * @return void
     */
    public function __constract(array $attributes = array()) {
      parent::__construct($attributes);
    }
    
    /**
     * boot
     *
     * @return void
     */
    protected static function boot()
    {
      parent::boot();
    }

    
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function source()
    {
      $this->source_class = config('backpack.store.source.class', 'Backpack\Store\app\Models\Source');
      return $this->belongsTo($this->source_class);
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    
    /**
     * getStatusAdminAttribute
     *
     * @return void
     */
    public function getStatusAdminAttribute() {
      if(isset($this->statuses[$this->status]) && !empty($this->statuses[$this->status])){
        $status_text = $this->statuses[$this->status];

        switch($this->status) {
          case 'pending':
            $color = 'gray';
            break;
          case 'done':
            $color = 'green';
            break;
          case 'error':
            $color = 'red';
            break;
          default:
            $color = 'black';
        }

        return "<span style='color: {$color};'>{$status_text}</span>";
      }else {
        return $this->status;
      }
    }
    
    /**
     * getCreatedAtHumanAttribute
     *
     * @return void
     */
    public function getCreatedAtHumanAttribute() {
      if($this->status === 'pending') {
        return $this->created_at->diffForHumans();
      }else {
        return $this->created_at;
      }
    }
    
    /**
     * getCountProcessedAdminAttribute
     *
     * @return void
     */
    public function getCountProcessedAdminAttribute() {
      return $this->processed_items . '/' . $this->total_items;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
