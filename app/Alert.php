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
        'yearmon', 'prov_id', 'alertsettings_id', 'ingest_id', 'modified_by', 'status'
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

    public function alertSetting()
    {
        return $this->belongsTo('App\AlertSetting', 'alertsettings_id');
    }

    public function ingest()
    {
        return $this->belongsTo('App\IngestLog', 'ingest_id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'modified_by');
    }

  // Shortcuts into the alert data since they can be
  // caused by alert-settings or failed-ingests
    public function institution()
    {
        if ($this->ingest_id == 0) {
            return $this->alertSetting->institution;
        } else {
            return $this->ingest->sushiSetting->institution;
        }
    }

    // public function detail()
    // {
    //     if ($this->ingest_id == 0) {
    //         return $this->alertSetting->metric->legend;
    //     } else {
    //         return $this->failedIngest->detail;
    //     }
    // }

    public function reportName()
    {
        if ($this->ingest_id == 0) {
            return $this->alertSetting->metric->report->name;
        } else {
            return $this->ingest->report->name;
        }
    }
}
