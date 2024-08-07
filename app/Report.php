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
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'legend', 'revision', 'parent_id', 'dorder', 'inherited_fields'
    ];
    protected $casts =['id'=>'integer', 'parent_id'=>'integer', 'dorder'=>'integer'];

    // Return the reportField relationship
    public function reportFields()
    {
        return $this->hasMany('App\ReportField');
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

    // Turn inherited_fields into key=>value array
    public function parsedInherited()
    {
        $_fields = array();
        foreach (preg_split('/,/', $this->inherited_fields) as $field) {
            $_f = preg_split('/:/', $field);
            if (!isset($_f[1])) {
                $_fields[$_f[0]] = null;
            } else {
                // allow for bracketed array of values
                if (preg_match("/\[(.*)\]/i", $_f[1], $matches)) {
                    $arr = array();
                    $values = preg_split("/,/", $matches[1]);
                    foreach ($values as $val) {
                        $arr[] = intval($val);
                    }
                     $_fields[$_f[0]] = $arr;
                } else {
                     $_fields[$_f[0]] = intval($_f[1]);
                }
            }
        }
        return $_fields;
    }

    // Return field-count for a master or child report
    public function fieldCount()
    {
        if ($this->parent_id == 0) {
            return sizeof($this->reportFields());
        } else {
            return sizeof(preg_split('/,/', $this->inherited_fields));
        }
    }
}
