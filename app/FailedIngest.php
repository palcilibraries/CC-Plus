<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FailedIngest extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'consodb';
    protected $table = 'failedingests';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
      'sushisettings_id', 'report_id', 'yearmon', 'process_step', 'retry_count', 'detail'
    ];

    public function sushisetting()
    {
        return $this->belongsTo('App\SushiSetting', 'sushisettings_id');
    }

    public function report()
    {
        return $this->belongsTo('App\Report', 'report_id');
    }

    public function alert()
    {
        return $this->hasOne('App\Alert', 'failed_id');
    }
}
