<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

// use ReportField;

class Report extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $connection = 'globaldb';
    protected $table = 'reports';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'legend', 'type', 'revision', 'parent_id', 'inherited_fields'
    ];

    // Return the reportField relationship
    public function reportFields()
    {
        return $this->hasMany('App\ReportField');
    }

    // Return a collection of fields, incudling inherited fields
    // (useful for handling custom or user-specific report layouts)
    public function allFields()
    {
       // Get a collection of ReportFields (not a relationship)
        if ($this->parent_id == 0) {
            return ReportField::where('report_id', '=', $this->id)->get();
        } else {
            return $this->inheritedFields();
        }
    }

    public function harvests()
    {
        return $this->hasMany('App\HarvestLog');
    }

    public function failedHarvests()
    {
        return $this->hasMany('App\FailedHarvest');
    }

    public function providers()
    {
        $_db = config('database.connections.consodb.database');
        return $this->belongsToMany('App\Provider', $_db . '.provider_report');
    }

    public function parent()
    {
       // Reports have at-most one parent
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children()
    {
       // Reports might have many children
        return $this->hasMany(static::class, 'parent_id')->orderBy('name', 'asc');
    }

    public function inheritedFields()
    {
       // decode the inherited_fields string to an array
        $_decoded = array();
        foreach (preg_split('/\,/', $this->inherited_fields) as $key => $value) {
            if (!strpos($value, ":")) {
                $_decoded[$value] = null;
                continue;
            }
            $_parts = preg_split('/:/', $value);
            $_decoded[$_parts[0]] = $_parts[1];
        }
        $field_ids = array_keys($_decoded);

       // Get and return matching fields
        return ReportField::whereIn('id', $field_ids)->get();
    }

}
