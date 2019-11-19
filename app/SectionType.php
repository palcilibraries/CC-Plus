<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SectionType extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
     protected $connection = 'globaldb';
     protected $table = 'sectiontypes';

     /**
      * The attributes that are mass assignable.
      *
      * @var array
      */
     protected $fillable = [ 'id', 'name'];

     public function titleReports()
     {
         return $this->hasMany('App\TitleReport');
     }

}
