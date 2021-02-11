<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /**
     *  Prevent primary key from auto-incrementing
     */
    public $incrementing = false;

    /**
     * The database table used by the model.
     */
    protected $connection = 'consodb';
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'id', 'name', 'description' ];
    protected $casts =['id'=>'integer'];

    public function users()
    {
        return $this
            ->belongsToMany('App\User')
            ->withTimestamps();
    }
}
