<?php

namespace App;
use Illuminate\Database\Eloquent\Model;

class GlobalProvider extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'globaldb';
    protected $table = 'global_providers';
    public $timestamps = false;

  /**
   * Mass assignable attributes.
   *
   * @var array
   */
    protected $attributes = ['name' => '', 'is_active' => 1, 'master_reports' => '{}', 'connectors' => '{}',
                             'server_url_r5' => ''];
    protected $fillable = ['id', 'name', 'is_active', 'master_reports', 'connectors', 'server_url_r5'];
    protected $casts = ['id'=>'integer', 'is_active'=>'integer', 'master_reports' => 'array', 'connectors' => 'array'];
}
