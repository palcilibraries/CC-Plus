<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AlertSetting extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'alertsettings';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
      'id', 'inst_id', 'is_active', 'field_id', 'variance', 'timespan'
    ];

    public function alerts()
    {
        return $this->hasMany('App\Alert');
    }

    public function institution()
    {
        return $this->belongsTo('App\Institution', 'inst_id');
    }

    public function reportfield()
    {
        return $this->belongsTo('App\ReportField', 'field_id');
    }

    public function canManage()
    {
      // Admin can manage any setting
        if (auth()->user()->hasRole("Admin")) {
            return true;
        }
      // Managers can only affect settings for their own institution
        if (auth()->user()->hasRole("Manager")) {
            return auth()->user()->inst_id == $this->inst_id;
        }

        return false;
    }
}
