<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
   /**
    * The database table used by the model.
    *
    * @var string
    */
    protected $connection = 'globaldb';
    protected $table = 'items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Title', 'authors', 'pub_date', 'article_version', 'DOI', 'PropID', 'ISBN', 'ISSN', 'eISSN', 'URI',
        'parent_id', 'parent_datatype_id', 'component_id', 'component_datatype_id'
    ];

    public function itemReports()
    {
        return $this->hasMany('App\ItemReport');
    }

    public function ident()
    {
        return $this->morphOne('App\Title', 'identifiers');
    }
}
