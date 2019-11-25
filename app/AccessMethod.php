<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccessMethod extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $connection = 'globaldb';
    protected $table = 'accessmethods';

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
