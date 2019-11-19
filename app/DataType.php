<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DataType extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
     protected $connection = 'globaldb';
     protected $table = 'datatypes';

     /**
      * The attributes that are mass assignable.
      *
      * @var array
      */
     protected $fillable = [ 'id', 'name'];

     public function databaseReports()
     {
         return $this->hasMany('App\DatabaseReport');
     }

     public function platformReports()
     {
         return $this->hasMany('App\PlatformReport');
     }

     public function titleReports()
     {
         return $this->hasMany('App\TitleReport');
     }

     public function itemReports()
     {
         return $this->hasMany('App\ItemReport');
     }

}
