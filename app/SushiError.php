<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SushiError extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $connection = 'globaldb';
    protected $table = 'sushierrors';

     /**
      * The attributes that are mass assignable.
      *
      * @var array
      */
    protected $fillable = [ 'id', 'message', 'severity'];

    public function failedIngests()
    {
        return $this->hasMany('App\FailedIngest');
    }
}
