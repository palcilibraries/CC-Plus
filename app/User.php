<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

// use AustinHeap\Database\Encryption\Traits\HasEncryptedAttributes;

class User extends Authenticatable
{
    use Notifiable;

    // use HasEncryptedAttributes;

    /**
     * The database table used by the model.
     */
    protected $connection = 'consodb';
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'name', 'email', 'email_verified_at', 'password', 'inst_id', 'phone',
        'optin_alerts', 'is_active', 'password_change_required'
    ];
    protected $casts =[
        'id'=>'integer', 'inst_id'=>'integer', 'optin_alerts'=>'integer', 'is_active'=>'integer',
        'password_change_required'=>'integer'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    // protected $casts = [
    //     'email_verified_at' => 'datetime',
    // ];

    /**
     * The attributes that should be encrypted on save (password is already hashed)
     * @var array
     */
    protected $encrypted = [ 'name', 'phone' ];

    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    public function canManage()
    {
      // ServerAdmin can manage any user and is only changeable by another ServerAdmin
        if (auth()->user()->hasRole("ServerAdmin")) {
            return true;
        } else {
            if ($this->roles()->where("name", "ServerAdmin")->first()) return false;
        }

      // Admin can manage any non-ServerAdmin user
        if (auth()->user()->hasRole("Admin")) return true;

      // Managers can manage users at their own inst, but not Admins
        if (auth()->user()->hasRole("Manager") &&
            !$this->roles()->where("name", "Admin")->orWhere("name", "ServerAdmin")->first()) {
            return auth()->user()->inst_id == $this->inst_id;
        }
      // Users can manage themselves
        return $this->id == auth()->id();
    }

    public function institution()
    {
        return $this->belongsTo('App\Institution', 'inst_id');
    }

    public function roles()
    {
        return $this
            ->belongsToMany('App\Role')
            ->withTimestamps();
    }

    public function alerts()
    {
        return $this->hasMany('App\Alert', 'modified_by');
    }

    public function savedReports()
    {
        return $this->hasMany('App\SavedReport', 'user_id');
    }

    public function authorizeRoles($roles)
    {
        if ($this->hasAnyRole($roles)) {
            return true;
        }
        abort(401, 'This action is unauthorized.');
    }

    public function hasAnyRole($roles)
    {
        if ($this->roles()->where("name", "ServerAdmin")->first()) return true;
        if (is_array($roles)) {
            if ($this->roles()->whereIn('name', $roles)->first()) return true;
        } else {
            if ($this->roles()->where('name', $roles)->first()) return true;
        }
        return 0;
    }

    public function maxRole()
    {
        return $this->roles()->max('role_id');
    }

    public function maxRoleName()
    {
        $_id = $this->roles()->max('role_id');
        $role = $this->roles()->where('role_id', $_id)->first();
        return $role->name;
    }

    public function hasRole($role)
    {
        if ($this->roles()->where("name", "ServerAdmin")->first()) return true;
        if ($this->roles()->where("name", $role)->first()) return true;
        return 0;
    }
}
