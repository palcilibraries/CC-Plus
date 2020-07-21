<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SystemAlert extends BaseModel
{
    /**
     * The database table used by the model.
     */
    protected $connection = 'consodb';
    protected $table = 'system_alerts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['is_active', 'severity_id', 'text', ];

    public function severity()
    {
        return $this->belongsTo('App\Severity', 'severity_id');
    }
}
