<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CcplusError extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $connection = 'globaldb';
    protected $table = 'ccplus_errors';

     /**
      * The attributes that are mass assignable.
      *
      * @var array
      */
    protected $fillable = [ 'id', 'message', 'severity_id'];

    public function severity()
    {
        return $this->belongsTo('App\Severity', 'severity_id');
    }

    public function failedHarvests()
    {
        return $this->hasMany('App\FailedHarvest');
    }
}
