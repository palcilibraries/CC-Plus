<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $connection = 'consodb';
    protected $table = 'institutions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
         'name', 'is_active', 'notes', 'type_id', 'password', 'sushiIPRange',
         'shibURL', 'fte'
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

    public function institutiontype()
    {
        return $this->belongsTo('App\InstitutionType', 'type_id');
    }

    public function institutiongroups()
    {
        return $this
            ->belongsToMany('App\InstitutionGroup')
            ->withTimestamps();
    }

    public function isAMemberOf($institutiongroup)
    {
        if ($this->institutiongroups()->where('institution_group_id', $institutiongroup)->first()) {
            return true;
        }
        return false;
    }

    public function users()
    {
        return $this->hasMany('App\User');
    }

    public function providers()
    {
        return $this->hasMany('App\Provider');
    }

    public function sushisettings()
    {
        return $this->hasMany('App\SushiSetting', 'inst_id');
    }

    public function alertsettings()
    {
        return $this->hasMany('App\AlertSetting', 'inst_id');
    }

    public function TRreports()
    {
        return $this->hasMany('App\TRreport');
    }

    public function DRreports()
    {
        return $this->hasMany('App\DRreport');
    }

    public function PRreports()
    {
        return $this->hasMany('App\PRreport');
    }

    public function IRreports()
    {
        return $this->hasMany('App\IRreport');
    }

//    public function ingestlogs() {
//        return $this->hasMany('App\IngestLog','inst_id');
//    }
}
