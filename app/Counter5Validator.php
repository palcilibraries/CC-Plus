<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Counter5Validator extends Model
{
    public $error = "Success";
    public $report;

  /**
   * Class Constructor and setting methods
   */
    public function __construct($_json_data)
    {
        $this->report = $_json_data;
    }

  // --------------------------------------------------------------
  // These are all just placeholders for a better, more coherant
  // approach. Laravel's rules-based validation functionality could
  // be a good approach. We'll probably want to "clean up" anything
  // that is fixable, and store the revised JSON in $this->report.
  // --------------------------------------------------------------
    // TR report validator
    // (temporary stub)
    public static function TR()
    {
        return true;
    }

    // DR report validator
    // (temporary stub)
    public static function DR()
    {
        return true;
    }

    // PR report validator
    // (temporary stub)
    public static function PR()
    {
        return true;
    }

    // IR report validator
    // (temporary stub)
    public static function IR()
    {
        return true;
    }
}
