<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
// use AustinHeap\Database\Encryption\Traits\HasEncryptedAttributes;

class SushiSetting extends Model
{
   // use HasEncryptedAttributes;

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
      'inst_id', 'prov_id', 'requestor_id', 'customer_id', 'API_key'
    ];

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

    public function harvestLogs()
    {
        return $this->hasMany('App\HarvestLog');
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
