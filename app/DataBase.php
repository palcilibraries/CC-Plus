<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DataBase extends Model
{
   /**
    * The database table used by the model.
    *
    * @var string
    */
    protected $connection = 'globaldb';
    protected $table = 'databases';

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = ['name', 'platform_id'];

    public function DRreports()
    {
        return $this->hasMany('App\DRreport');
    }
}
