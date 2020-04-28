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
      'title', 'user_id', 'months', 'master_id', 'inherited_fields'
    ];

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function master()
    {
        return $this->belongsTo('App\Report', 'master_id');
    }

    public function canManage()
    {
      // Admin can manage anything
        if (auth()->user()->hasRole("Admin")) {
            return true;
        }
      // Managers can manage reports for their own inst
        if (auth()->user()->hasRole("Manager")) {
            return auth()->user()->inst_id == $this->user->inst_id;
        }
      // Users can manage their own reports
        return $this->user_id == auth()->id();
    }

}
