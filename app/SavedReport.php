<?php

namespace App;

use App\ReportFilter;
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
      'title', 'user_id', 'date_range', 'master_id', 'report_id', 'ym_from', 'ym_to', 'inherited_fields', 'filters'
    ];

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function report()
    {
        return $this->belongsTo('App\Report', 'report_id');
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
        foreach (preg_split('/\+/', $this->filters) as $filter) {
            $_f = preg_split('/:/', $filter);
            if (!isset($_f[1])) {
                $return_filters[$_f[0]] = null;
            } else {
                // allow for bracketed array of values
                if (preg_match("/\[(.*)\]/i", $_f[1], $matches)) {
                    $arr = array();
                    $values = preg_split("/,/", $matches[1]);
                    foreach ($values as $val) {
                        $arr[] = intval($val);
                    }
                    $return_filters[$_f[0]] = $arr;
                } else {
                    $return_filters[$_f[0]] = intval($_f[1]);
                }
            }
        }
        return $return_filters;
    }

    // Return a filters array that matches the vue-datastore filter_by object
    public function filterBy()
    {
        $return_filters = array('report_id' => $this->master->id);
        $return_filters['toYM'] = date("Y-m", strtotime("-1 month"));
        $return_filters['fromYM'] = date("Y-m", strtotime("-" . $this->months . " months"));

        // Define the filter presets for this SavedReport
        $my_filters = $this->parsedFilters();
        if (count($my_filters) > 0) {
            foreach ($my_filters as $key => $value) {
                $rf = ReportFilter::where('id', $key)->first();
                if ($rf) {
                    $return_filters[$rf->report_column] = $value;
                }
            }
        }

        // Ensure that all master field filters exist in $return_filters
        $fields = $this->master->reportFields;
        $fields->load('reportFilter');
        foreach ($fields as $field) {
            if ($field->reportFilter) {
                $_col = $field->reportFilter->report_column;
                if (!isset($return_filters[$_col])) {
                    if ($_col == 'inst_id' || $_col == 'prov_id' || $_col == 'plat_id' || $_col == 'yop') {
                        $return_filters[$_col] = [];
                    } else {
                        $return_filters[$_col] = 0;
                    }
                }
            }
        }
        if (!isset($return_filters['institutiongroup_id'])) {   // This isn't a field, just a filter
            $return_filters['institutiongroup_id'] = 0;
        }
        return $return_filters;
    }
}
