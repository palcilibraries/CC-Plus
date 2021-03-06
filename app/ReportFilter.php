<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReportFilter extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'globaldb';
    protected $table = 'reportfilters';
    public $timestamps = false;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = ['attrib', 'model', 'table_name', 'report_column'];

    public function reportField()
    {
        return $this->hasMany('App\ReportField', 'report_filter_id');
    }
}
