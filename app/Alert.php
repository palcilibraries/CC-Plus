<?php

namespace App;

// use Illuminate\Database\Eloquent\Model;
// use BaseModel;

class Alert extends BaseModel
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'consodb';
    protected $table = 'alerts';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
    'yearmon', 'alertsettings_id', 'failed_id', 'modified_by', 'status'
    ];

    public function canManage()
    {
      // Admin can manage any institution
        if (auth()->user()->hasRole("Admin")) {
            return true;
        }
      // Managers can only manage their own institution
        if (auth()->user()->hasRole("Manager")) {
            return auth()->user()->inst_id == $this->id;
        }

        return false;
    }

    public function provider()
    {
        return $this->belongsTo('App\Provider', 'prov_id');
    }

    public function alertsetting()
    {
        return $this->belongsTo('App\AlertSetting', 'alertsettings_id');
    }

    public function failedingest()
    {
        return $this->belongsTo('App\FailedIngest', 'failed_id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'modified_by');
    }

  // Shortcuts into the alert data since they can be
  // caused by alert-settings or failed-ingests
    public function institution()
    {
        if ($this->failed_id == 0) {
            return $this->failedingest->alertsetting->institution;
        } else {
            return $this->failedingest->sushisetting->institution;
        }
    }

    public function detail()
    {
        if ($this->failed_id == 0) {
            return $this->alertsetting->metric->legend;
        } else {
            return $this->failedingest->detail;
        }
    }

    public function reportName()
    {
        if ($this->failed_id == 0) {
            return $this->alertsetting->metric->report->name;
        } else {
            return $this->failedingest->report->name;
        }
    }
}
