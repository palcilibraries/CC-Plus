<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SushiSetting extends Model
{
   /**
   * Class Constructor
   */
    private $global_connectors;
    public function __construct(array $attributes = array())
    {
       parent::__construct($attributes);
       $this->global_connectors = ConnectionField::get();
    }

  /**
   * The database table used by the model.
   *
   * @var string
   */
    protected $connection = 'consodb';
    protected $table = 'sushisettings';

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
    protected $fillable = [
      'inst_id', 'prov_id', 'requestor_id', 'customer_id', 'api_key', 'extra_args', 'support_email', 'status'
    ];
    protected $casts =['id'=>'integer', 'inst_id'=>'integer', 'prov_id'=>'integer'];

  /**
   * The attributes that should be encrypted on save (password is already hashed)
   *
   * @var array
   */
    protected $encrypted = [ 'requestor_id', 'customer_id', 'api_key' ];

    public function institution()
    {
        return $this->belongsTo('App\Institution', 'inst_id');
    }

    public function provider()
    {
        return $this->belongsTo('App\GlobalProvider', 'prov_id');
    }

    public function isComplete()
    {
        $required = $this->provider->connectors;
        $connectors = $this->global_connectors->whereIn('id',$required)->pluck('name')->toArray();
        foreach ($connectors as $cnx) {
            if (is_null($this->$cnx) || trim($this->$cnx) == '' || $this->$cnx == '-required-') return false;
        }
        return true;
    }

    public function harvestLogs()
    {
        return $this->hasMany('App\HarvestLog', 'sushisettings_id');
    }

    public function failedHarvests()
    {
        return $this->hasMany('App\FailedHarvest');
    }

    public function alerts()
    {
        return $this->hasMany('App\Alert');
    }

    public function canManage()
    {
      // Admins manage
        if (auth()->user()->hasRole("Admin")) {
            return true;
        }
      // Managers can manage settings for their own inst
        if (auth()->user()->hasRole("Manager")) {
            return (auth()->user()->inst_id == $this->inst_id);
        }
        return false;
    }

    // Resets status and connection fields when updating or an institution or provider status is made Active
    public function resetStatus( $update_disabled=false )
    {
        // if not authorized or setting is Disabled, bail out silently
        if (!$this->canManage() || ($this->status == "Disabled" && !$update_disabled)) return;

        // new status is Suspended if either provider or inst is INactive
        $new_status = ($this->institution->is_active && $this->provider->is_active) ? 'Enabled' : 'Suspended';
        if ($this->isComplete() || $new_status == 'Suspended') {
            // Inst+Prov active and settings complete sets to "Enabled", otherwise sets to "Suspended"
            $this->status = $new_status;
        // Getting here means inst+prov are both Active but settings are incomplete. update the field values
        } else {
            $required = $this->provider->connectors;
            $fields = $this->global_connectors->whereIn('id',$required);
            $this->status = 'Incomplete';
            foreach ($fields as $fld) {
                $name = $fld->name;
                if ($this->$name == null || $this->$name == '') {
                    $this->$name = "-required-";
                }
            }
        }
        $this->save();
    }
}
