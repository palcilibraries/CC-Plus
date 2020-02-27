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
        'name', 'email', 'email_verified_at', 'password', 'inst_id', 'phone',
        'optin_alerts', 'is_active', 'password_change_required'
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
      // Admin can manage any user
        if (auth()->user()->hasRole("Admin")) {
            return true;
        }
      // Managers can manage users at their own inst, but not Admins
        if (auth()->user()->hasRole("Manager") && !$this->hasRole("Admin")) {
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
        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role)) {
                    return true;
                }
            }
        } else {
            if ($this->hasRole($roles)) {
                return true;
            }
        }
        return false;
    }

    public function maxRole()
    {
        return $this->roles()->max('role_id');
    }

    public function hasRole($role)
    {
        if ($this->roles()->where("name", $role)->first()) {
            return true;
        }
        return false;
    }
}
