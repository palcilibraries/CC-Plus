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
    protected $fillable = ['report_id', 'is_global', 'table_name', 'report_column'];

    public function report()
    {
        return $this->belongsTo('App\Report', 'report_id');
    }
}
