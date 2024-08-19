<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PlatformReport extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'pr_report_data';
    public $timestamps = false;

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
       'plat_id', 'prov_id', 'inst_id', 'yearmon', 'datatype_id', 'accessmethod_id', 'searches_platform',
       'total_item_investigations', 'total_item_requests', 'unique_item_investigations',
       'unique_item_requests', 'unique_title_investigations', 'unique_title_requests'
    ];

    public function platform()
    {
        return $this->belongsTo('App\Platform', 'plat_id');
    }

    public function provider()
    {
        return $this->belongsTo('App\GlobalProvider', 'prov_id');
    }

    public function institution()
    {
        return $this->belongsTo('App\Institution' . 'inst_id');
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
