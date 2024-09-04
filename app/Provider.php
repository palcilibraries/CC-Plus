<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

// use AustinHeap\Database\Encryption\Traits\HasEncryptedAttributes;

class Provider extends Model
{
   // use HasEncryptedAttributes;

  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'providers';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $attributes = ['name' => '', 'is_active' => 1, 'inst_id' => 1, 'global_id' => null, 'allow_inst_specific' => 0];
    protected $fillable = [ 'name', 'is_active', 'inst_id', 'global_id', 'allow_inst_specific' ];
    protected $casts = ['id'=>'integer', 'is_active'=>'integer', 'inst_id'=>'integer', 'global_id' => 'integer',
                        'allow_inst_specific' => 'integer'];

  /**
   * The attributes that should be encrypted on save (password is already hashed)
   *
   * @var array
   */
    public function canManage()
    {
      // Admin can manage any provider
        if (auth()->user()->hasRole("Admin")) {
            return true;
        }
      // Managers can manage providers for their own inst
        if (auth()->user()->hasRole("Manager")) {
            return (auth()->user()->inst_id == $this->inst_id);
        }
        return false;
    }

    public function institution()
    {
        return $this->belongsTo('App\Institution', 'inst_id');
    }

    public function alerts()
    {
        return $this->hasMany('App\Alert', 'prov_id');
    }

    public function reports()
    {
        $_db = config('database.connections.consodb.database');
        return $this
          ->belongsToMany('App\Report', $_db . '.provider_report')
          ->withTimestamps();
    }

    public function globalProv()
    {
        return $this->belongsTo('App\GlobalProvider', 'global_id');
    }

    public function data()
    {
        if (!is_null($this->global_id)) {
            $globalProv = $this->globalProv()->first()->toArray();
            return array_merge($this->globalProv()->first()->toArray(),$this->toArray());
        } else {
            return $this->toArray();
        }
    }
}
