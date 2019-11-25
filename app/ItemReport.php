<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ItemReport extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'ir_report_data';

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
         'item_id', 'prov_id', 'plat_id', 'inst_id', 'yearmon', 'datatype_id', 'accesstype_id', 'accessmethod_id',
         'total_item_requests', 'unique_item_requests'
    ];

    public function items()
    {
        return $this->belongsToMany('App\Item', 'item_id');
    }

    public function providers()
    {
        return $this->belongsToMany('App\Provider', 'prov_id');
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

    public function accessType()
    {
        return $this->belongsTo('App\AccessType', 'accesstype_id');
    }

    public function dataType()
    {
        return $this->belongsTo('App\DataType', 'datatype_id');
    }
}
