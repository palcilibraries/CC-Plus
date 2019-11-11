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
        'name', 'platform_id', 'authors', 'pub_date', 'article_version', 'DOI',
        'PropID', 'ISBN', 'ISSN', 'eISSN', 'URI',  'parent_title', 'parent_authors',
        'parent_article_version', 'parent_DOI', 'parent_PropID', 'parent_ISSN',
        'parent_eISSN', 'parent_URI', 'data_type', 'access_type',
    ];

    public function itemReports()
    {
        return $this->hasMany('App\ItemReport');
    }
}
