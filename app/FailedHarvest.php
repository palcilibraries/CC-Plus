<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FailedHarvest extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'consodb';
    protected $table = 'failedharvests';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
      'harvest_id', 'process_step', 'error_id', 'detail', 'help_url'
    ];

    public function harvest()
    {
        return $this->belongsTo('App\HarvestLog', 'harvest_id');
    }

    public function ccplusError()
    {
        return $this->belongsTo('App\CcplusError', 'error_id');
    }
}
