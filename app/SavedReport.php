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
      'title', 'user_id', 'months', 'master_id', 'inherited_fields', 'filters'
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

    // Turn filters into key=>value array
    public function parsedFilters()
    {
        $return_filters = array();
        foreach (preg_split('/,/', $this->filters) as $filter) {
            $_f = preg_split('/:/',$filter);
            $return_filters[$_f[0]] = (isset($_f[1])) ? $_f[1] : null;
        }
        return $return_filters;
    }
}
