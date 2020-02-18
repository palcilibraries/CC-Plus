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

    public function institutionType()
    {
        return $this->belongsTo('App\InstitutionType', 'type_id');
    }

    public function institutionGroups()
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

    public function sushiSettings()
    {
        return $this->hasMany('App\SushiSetting', 'inst_id');
    }

    public function alertSettings()
    {
        return $this->hasMany('App\AlertSetting', 'inst_id');
    }

    public function titleReports()
    {
        return $this->hasMany('App\TitleReport');
    }

    public function databaseReports()
    {
        return $this->hasMany('App\DatabaseReport');
    }

    public function platformReports()
    {
        return $this->hasMany('App\PlatformReport');
    }

    public function itemReports()
    {
        return $this->hasMany('App\ItemReport');
    }

}
