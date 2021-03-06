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
