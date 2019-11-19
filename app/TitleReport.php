<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TitleReport extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'tr_report_data';

    /**
     * Mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
         'jrnl_id', 'book_id', 'prov_id', 'publisher_id', 'plat_id', 'inst_id', 'yearmon', 'datatype_id',
         'sectiontype_id', 'YOP', 'accesstype_id', 'accessmethod_id', 'total_item_investigations', 'total_item_requests',
         'unique_item_investigations', 'unique_item_requests', 'unique_title_investigations', 'unique_title_requests',
         'limit_exceeded', 'not_license'
    ];

    public function journals()
    {
        return $this->belongsToMany('App\Journal', 'jrnl_id');
    }

    public function books()
    {
        return $this->belongsToMany('App\Book', 'book_id');
    }

    public function providers()
    {
        return $this->belongsToMany('App\Provider', 'prov_id');
    }

    public function publishers()
    {
        return $this->belongsToMany('App\Publisher', 'publisher_id');
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

    public function sectionType()
    {
        return $this->belongsTo('App\SectionType', 'sectiontype_id');
    }
}
