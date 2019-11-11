<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReportField extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'globaldb';
    protected $table = 'reportfields';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = ['report_id', 'legend'];

    public function report()
    {
        return $this->belongsTo('App\Report', 'report_id');
    }

    public function savedReports()
    {
        $_db = config('database.connections.consodb.database');
        return $this
           ->belongsToMany('App\SavedReport', $_db . '.savedreport_reportfield')
           ->withTimestamps();
    }

    public function alertSettings()
    {
        return $this->hasMany('App\AlertSetting');
    }
}
