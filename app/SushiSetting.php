<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

// use AustinHeap\Database\Encryption\Traits\HasEncryptedAttributes;

class SushiSetting extends Model
{
   // use HasEncryptedAttributes;
   /**
   * Class Constructor
   */
    private $global_connectors;
    public function __construct(array $attributes = array())
    {
       parent::__construct($attributes);
       $this->global_connectors = ConnectionField::get();
    }

  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'sushisettings';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
      'inst_id', 'prov_id', 'requestor_id', 'customer_id', 'API_key', 'extra_args', 'support_email', 'status'
    ];
    protected $casts =['id'=>'integer', 'inst_id'=>'integer', 'prov_id'=>'integer'];

  /**
   * The attributes that should be encrypted on save (password is already hashed)
   *
   * @var array
   */
    protected $encrypted = [ 'requestor_id', 'customer_id', 'API_key' ];

    public function institution()
    {
        return $this->belongsTo('App\Institution', 'inst_id');
    }

    public function provider()
    {
        return $this->belongsTo('App\Provider', 'prov_id');
    }

    public function isComplete()
    {
        $required = $this->provider->globalProv->connectors;
        $connectors = $this->global_connectors->whereIn('id',$required)->pluck('name')->toArray();
        foreach ($connectors as $cnx) {
            if (is_null($this->$cnx) || trim($this->$cnx) == '' || $this->$cnx == '-missing-') return false;
        }
        return true;
    }

    public function harvestLogs()
    {
        return $this->hasMany('App\HarvestLog', 'sushisettings_id');
    }

    public function failedHarvests()
    {
        return $this->hasMany('App\FailedHarvest');
    }

    public function alerts()
    {
        return $this->hasMany('App\Alert');
    }
}
