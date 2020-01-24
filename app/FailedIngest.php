<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FailedIngest extends Model
{
  /**
   * The database table used by the model.
   */
    protected $connection = 'consodb';
    protected $table = 'failedingests';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
      'ingest_id', 'process_step', 'error_id', 'detail'
    ];

    public function ingest()
    {
        return $this->belongsTo('App\IngestLog', 'ingest_id');
    }

    public function ccplusError()
    {
        return $this->belongsTo('App\CcplusError', 'error_id');
    }
}
