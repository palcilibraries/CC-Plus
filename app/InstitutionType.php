<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InstitutionType extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'consodb';
    protected $table = 'institutiontypes';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [ 'id', 'name' ];
    protected $casts =['id'=>'integer'];

    public function institutions()
    {
        return $this->hasMany('App\Institution');
    }
}
