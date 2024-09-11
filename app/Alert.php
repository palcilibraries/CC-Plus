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
        'yearmon', 'prov_id', 'alertsettings_id', 'harvest_id', 'modified_by', 'status'
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
        return $this->belongsTo('App\GlobalProvider', 'prov_id');
    }

    public function alertSetting()
    {
        return $this->belongsTo('App\AlertSetting', 'alertsettings_id');
    }

    public function harvest()
    {
        return $this->belongsTo('App\HarvestLog', 'harvest_id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'modified_by');
    }

   // Shortcuts for the alert and institution records since they are related
   // to either an alert-setting or to a failed harvest
    public function institution()
    {
        if (is_null($this->harvest_id)) {
            return $this->alertSetting->institution;
        } else {
            return $this->harvest->sushiSetting->institution;
        }
    }

    public function report()
    {
        if (is_null($this->harvest_id)) {
            return $this->alertSetting->reportField->report;
        } else {
            return $this->harvest->report;
        }
    }
}
