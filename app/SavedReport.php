<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SavedReport extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'savedreports';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
      'title', 'user_id', 'months'
    ];

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function reportFields()
    {
        return $this->belongsToMany('App\ReportField')->withTimestamps();
    }
}
