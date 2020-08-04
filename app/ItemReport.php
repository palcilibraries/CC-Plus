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
    public $timestamps = false;

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'item_id', 'prov_id', 'plat_id', 'inst_id', 'yearmon', 'yop', 'datatype_id', 'accesstype_id',
        'accessmethod_id', 'total_item_requests', 'unique_item_requests', 'total_item_investigations',
        'unique_item_investigations', 'limit_exceeded', 'not_license'
    ];

    public function item()
    {
        return $this->belongsTo('App\Item', 'item_id');
    }

    public function provider()
    {
        return $this->belongsTo('App\Provider', 'prov_id');
    }

    public function platform()
    {
        return $this->belongsTo('App\Platform', 'plat_id');
    }

    public function institution()
    {
        return $this->belongsTo('App\Institution', 'inst_id');
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
