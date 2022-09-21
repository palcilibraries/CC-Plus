<?php

namespace App;
use Illuminate\Database\Eloquent\Model;

class GlobalSetting extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
      protected $connection = 'globaldb';
      protected $table = 'global_settings';

      public $timestamps = false;

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
      protected $fillable = ['id', 'name', 'value'];

}
