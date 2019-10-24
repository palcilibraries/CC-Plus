<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'globaldb';
    protected $table = 'platforms';

  /**
   * Mass assignable attributes.
   *
   * @var array
   */
    protected $fillable = ['id', 'name'];

  /**
   * Methods for connections to reports
   */
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
}
