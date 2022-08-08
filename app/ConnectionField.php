<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectionField extends Model
{
  /**
   * The database table used by the model.
   *
   * @var string
   */
  protected $connection = 'globaldb';
  protected $table = 'connection_fields';

   /**
    * The attributes that are mass assignable.
    *
    * @var array
    */
  protected $fillable = [ 'id', 'name', 'label'];

  public function providers()
  {
      $_db = config('database.connections.consodb.database');
      return $this->belongsToMany('App\Provider', $_db . '.provider_connectors');
  }

}
