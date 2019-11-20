<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DatabaseReport extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'dr_report_data';

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
         'db_id', 'prov_id', 'publisher_id', 'plat_id', 'inst_id', 'yearmon', 'datatype_id', 'accessmethod_id',
         'searches_automated', 'searches_federated', 'searches_regular', 'total_item_investigations',
         'total_item_requests', 'unique_item_investigations', 'unique_item_requests', 'unique_title_investigations',
         'unique_title_requests', 'limit_exceeded', 'not_license'
    ];

    public function databases()
    {
        return $this->belongsToMany('App\DataBase', 'db_id');
    }

    public function providers()
    {
        return $this->belongsToMany('App\Provider', 'prov_id');
    }

    public function publishers()
    {
        return $this->belongsToMany('App\Platform', 'publisher_id');
    }

    public function platforms()
    {
        return $this->belongsToMany('App\Platform', 'plat_id');
    }

    public function institutions()
    {
        return $this->belongsToMany('App\Institution', 'inst_id');
    }

    public function accessMethod()
    {
        return $this->belongsTo('App\AccessMethod', 'accessmethod_id');
    }

    public function dataType()
    {
        return $this->belongsTo('App\DataType', 'datatype_id');
    }

}
