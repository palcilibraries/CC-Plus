<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StagedReport extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'XML_File', 'CSV_File', 'report_ID', 'yearmon', 'con_key', 'prov_id',
        'inst_id',
    ];
}
