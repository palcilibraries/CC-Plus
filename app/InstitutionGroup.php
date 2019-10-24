<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InstitutionGroup extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'consodb';
    protected $table = 'institutiongroups';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [ 'id', 'name' ];

    public function institutions()
    {
        return $this
          ->belongsToMany('App\Institution')
          ->withTimestamps();
    }
}
