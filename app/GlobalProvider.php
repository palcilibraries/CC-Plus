<?php

namespace App;
use App\ConnectionField;
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
    protected $attributes = ['registry_id' => null, 'name' => '', 'abbrev' => null, 'is_active' => 1, 'refreshable' => 1,
                             'master_reports' => '{}', 'connectors' => '{}', 'server_url_r5' => '', 'notifications_url' => null,
                             'platform_parm' => null];
    protected $fillable = ['id', 'registry_id', 'name', 'abbrev', 'is_active', 'master_reports', 'connectors', 'server_url_r5',
                           'notifications_url', 'platform_parm'];
    protected $casts = ['id'=>'integer', 'is_active'=>'integer', 'refreshable'=>'integer', 'master_reports' => 'array',
                        'connectors' => 'array'];

    // Return the ConnectionField detail based on connectors array
    public function connectionFields()
    {
        $cnxs = $this->connectors;
        if (count($cnxs) == 0) return [];
        return ConnectionField::whereIn('id',$cnxs)->get()->toArray();
    }
}
