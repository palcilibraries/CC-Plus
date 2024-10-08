<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Consortium;
use App\Report;
use App\GlobalSetting;
use App\GlobalProvider;
use App\ConnectionField;
use DB;

class GlobalAdminController extends Controller
{
    private $masterReports;
    private $allConnectors;

    public function __construct()
    {
        $this->middleware(['auth','role:ServerAdmin']);
    }

    /**
     * Index method for GlobalAdmin Controller
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        global $masterReports, $allConnectors;

        // Get consortia, master reports, and connection fields
        $consortia = Consortium::orderby('name')->get();
        $this->getMasterReports();
        $this->getConnectionFields();
        $all_connectors = $allConnectors->toArray();

        // Get global settings, minus the server admin credentials
        $skip_vars = array('server_admin','server_admin_pass');
        $settings = GlobalSetting::whereNotIn('name',$skip_vars)->pluck('value', 'name')->toArray();

        // Get global providers and preserve the current instance database setting
        $gp_data = GlobalProvider::orderBy('name', 'ASC')->get();

        // Build the providers array to pass onto the view
        $providers = array();
        foreach ($gp_data as $gp) {
            $provider = $gp->toArray();
            $provider['status'] = ($gp->is_active) ? "Active" : "Inactive";

            // Build arrays of booleans for connecion fields and reports for the U/I chackboxes
            $provider['connector_state'] = $this->connectorState($gp->connectors);
            $provider['report_state'] = $this->reportState($gp->master_reports);

            // Set connection field labels in an array for the datatable display
            $provider['connection_fields'] = array();
            foreach ($allConnectors as $fld) {
                if ( in_array($fld->id, $gp->connectors) ) {
                    $provider['connection_fields'][] = $fld->label;
                }
            }

            // Walk all instances scan for harvests connected to this provider
            // If any are found, the can_delete flag will be set to false to disable deletion option in the U/I
            $provider['can_delete'] = true;
            $provider['connection_count'] = 0;
            $connections = array();
            foreach ($consortia as $instance) {
                // Collect details from the instance for this provider
                $details = $this->instanceDetails($instance->ccp_key, $gp);
                if ($details['harvest_count'] > 0) {
                    $provider['can_delete'] = false;
                }
                if ($details['connections'] > 0) {
                    $connections[] = array('key'=>$instance->ccp_key, 'name'=>$instance->name, 'num'=>$details['connections']);
                    $provider['connection_count'] += 1;
                }
            }
            $provider['connections'] = $connections;
            $provider['updated'] = (is_null($gp->updated_at)) ? null : date("Y-m-d h:ia", strtotime($gp->updated_at));
            $providers[] = $provider;
        }

        $filters = array('stat' => null);
        return view('globaladmin.home', compact('consortia','settings','providers','filters','masterReports','all_connectors'));
    }

    /**
     * Change instnance method for GlobalAdmin Controller
     *
     * @return \Illuminate\Http\Response
     */
    public function changeInstance($key)
    {
        // Get input arguments from the request
        $consortium = Consortium::where('ccp_key',$key)->first();
        if (!$consortium) {
            return response()->json(['result' => 'Instance not found!']);
        }

        // Update the active configuration and the sesttion to use the new key
        $conso_db = "ccplus_" . $key;
        config(['database.connections.consodb.database' => $conso_db]);
        session(['ccp_con_key' => $key]);
        try {
            DB::reconnect('consodb');
        } catch (\Exception $e) {
            return response()->json(['result' => 'Error decoding input!']);
        }
        return redirect()->route('admin.home');
    }

    /**
     * Pull and re-order master reports and store in private global
     */
    private function getMasterReports() {
        global $masterReports;
        $masterReports = Report::where('revision',5)->where('parent_id',0)->orderBy('dorder','ASC')->get(['id','name']);
    }

    /**
     * Pull and re-order master reports and store in private global
     */
    private function getConnectionFields() {
        global $allConnectors;
        $allConnectors = ConnectionField::get();
    }

    /**
     * Return an array of booleans for report-state from provider reports columns
     *
     * @param  Array  $reports
     * @return Array  $report-state
     */
    private function reportState($reports) {
        global $masterReports;
        $rpt_state = array();
        foreach ($masterReports as $rpt) {
            $rpt_state[$rpt->name] = (in_array($rpt->id, $reports)) ? true : false;
        }
        return $rpt_state;
    }

    /**
     * Return an array of booleans for connector-state from provider connectors columns
     *
     * @param  Array  $connectors
     * @return Array  $connector-state
     */
    private function connectorState($connectors) {
      global $allConnectors;
      $cnx_state = array();
      foreach ($allConnectors as $fld) {
          $cnx_state[$fld->name] = (in_array($fld->id, $connectors)) ? true : false;
      }
      return $cnx_state;
    }

    /**
     * Return an array of booleans for connector-state from provider connectors columns
     *
     * @param  String  $instanceKey
     * @param  GlobalProvider  $gp
     * @return Array  $details
     */
    private function instanceDetails($instanceKey, $gp) {

        // Query the tables directly for what we're after, starting with connection count
        $qry = "Select count(*) as num from ccplus_" . $instanceKey . ".sushisettings where prov_id = " . $gp->id;
        $result = DB::select($qry);
        $connections = $result[0]->num;

        // Get the number of harvests
        $qry .= " and last_harvest is not null";
        $result = DB::select($qry);
        $count = $result[0]->num;

        // return the numbers
        return array('harvest_count' => $count , 'connections' => $connections);
    }
}
