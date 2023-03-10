<?php

namespace App\Http\Controllers;

use App\GlobalSetting;
use Illuminate\Http\Request;
use \Illuminate\Contracts\Cache\Factory;

class GlobalSettingController extends Controller
{
  /**
   * Index method for GlobalSetting Controller
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
      // Get settings as an associative array of name => value
      $skip_vars = array('server_admin','server_admin_pass');
      $settings = GlobalSetting::whereNotIn('name',$skip_vars)->pluck('value', 'name')->toArray();
      return view('globalsettings.index', compact('settings'));
  }

  /**
   * Store / Replace ALL global setting variable values
   * @param  \Illuminate\Http\Request  $request
   * @return JSON
   */
  public function store(Request $request, Factory $cache)
  {
     // Validate form inputs
      $this->validate($request, ['all_globals' => 'required']);
      $input = $request->all();

      // Get all current settings
      $settings = GlobalSetting::get();
      if (!$settings) {
          return response()->json(['result' => false, 'msg' => 'Error pulling current settings!!']);
      }

      // update and save them all
      foreach ($settings as $setting) {
          if (isset($input['all_globals'][$setting->name])) {
              $setting->value = $input['all_globals'][$setting->name];
              $setting->save();
          }
      }

      // Clear the 'ccplus' section of the cached configuration
      $cache->forget('ccplus');
      return response()->json(['result' => true, 'msg' => 'Settings successfully updated!']);
  }

}
