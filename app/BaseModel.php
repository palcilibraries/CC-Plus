<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class BaseModel extends Model
{
  /**
   * Retrieves the acceptable enum fields for a column
   *
   * @param string $column Column name
   *
   * @return array
   */
    public static function getEnumValues($column)
    {
        // Create an instance of the model to be able to get the table name
        $instance = new static();
        // Target table, make it dependent on DB-connection DB
        $_table = \Config::get('database.connections.' . $instance->connection)['database'] .
                      '.' . $instance->getTable();
        $enumStr = DB::select(DB::raw('SHOW COLUMNS FROM ' . $_table . ' WHERE Field = "' . $column . '"'))[0]->Type;
        preg_match_all("/'([^']+)'/", $enumStr, $matches);

        // Return matches
        return isset($matches[1]) ? $matches[1] : [];
    }
}
