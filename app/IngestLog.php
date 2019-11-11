<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IngestLog extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'consodb';
    protected $table = 'ingestlogs';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
    'sushisettings_id', 'report_id', 'yearmon', 'status'
    ];

    public function report()
    {
        return $this->belongsTo('App\Report', 'report_id');
    }

    public function sushiSetting()
    {
        return $this->belongsTo('App\SushiSetting', 'sushisettings_id');
    }
}
