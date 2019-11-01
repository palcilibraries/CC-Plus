<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
   /**
    * The database table used by the model.
    *
    * @var string
    */
    protected $connection = 'globaldb';
    protected $table = 'platforms';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Title', 'ISBN'
    ];

    public function TRreports()
    {
        return $this->hasMany('App\TRreport');
    }
}
