<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
   /**
    * The database table used by the model.
    *
    * @var string
    */
    protected $connection = 'globaldb';
    protected $table = 'journals';

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'Title', 'ISSN', 'eISSN'
    ];

    public function TRreports()
    {
        return $this->hasMany('App\TRreport');
    }
}
