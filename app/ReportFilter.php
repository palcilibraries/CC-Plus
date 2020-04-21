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

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = ['is_global', 'table_name', 'report_column'];

    public function reportField()
    {
        return $this->belongsToMany('App\ReportField', 'report_filter_id');
    }
}
