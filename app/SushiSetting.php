<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SushiSetting extends Model
{
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

    public function institution()
    {
        return $this->belongsTo('App\Institution', 'inst_id');
    }

    public function provider()
    {
        return $this->belongsTo('App\Provider', 'prov_id');
    }

    public function ingestlogs()
    {
        return $this->hasMany('App\IngestLog');
    }

    public function failedingests()
    {
        return $this->hasMany('App\FailedIngest');
    }

    public function alerts()
    {
        return $this->hasMany('App\Alert');
    }
}
