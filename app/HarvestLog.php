<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HarvestLog extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'consodb';
    protected $table = 'harvestlogs';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
        'status', 'sushisettings_id', 'report_id', 'yearmon', 'attempts', 'error_id', 'rawfile'
    ];

    public function failedHarvests()
    {
        return $this->hasMany('App\FailedHarvest', 'harvest_id');
    }

    public function report()
    {
        return $this->belongsTo('App\Report', 'report_id');
    }

    public function sushiSetting()
    {
        return $this->belongsTo('App\SushiSetting', 'sushisettings_id');
    }

    public function canManage()
    {
      // Admins can manage any record
        if (auth()->user()->hasRole("Admin")) {
            return true;
        }
      // Managers can manage harvests for their own inst only
        if (auth()->user()->hasRole("Manager")) {
            return auth()->user()->inst_id == $this->sushiSetting->inst_id;
        }
      // Otherwise, return false
        return false;
    }

    public function lastError()
    {
        return $this->belongsTo('App\CcplusError', 'error_id');
    }
}
