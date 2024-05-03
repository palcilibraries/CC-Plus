<?php

// app/Http/Controllers/ConsortiumController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\StreamOutput;
use DB;
use Hash;
use App\Consortium;

class ConsortiumController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JSON
     */
    public function store(Request $request)
    {
        //Validate request and put fields into $input
        $this->validate($request, [
            'ccp_key' => 'required|max:10',
            'name' => 'required',
            'email' => 'required',
            'admin_user' => 'required',
            'admin_pass' => 'required',
            'admin_confirm_pass' => 'required',
            ]);
        $input = $request->all();

        // Make sure the database table name will work
        $conso_db = "ccplus_" . $input['ccp_key'];
        $count = DB::table('information_schema.tables')->where("TABLE_SCHEMA", "=", $conso_db)->count();
        if ($count > 0) {
            return response()->json(['result' => false, 'msg' => 'Another database named ' . $conso_db . ' already exists!']);
        }

        // Get server admin credential
        $server_admin = config('ccplus.server_admin');
        $server_admin_pass = config('ccplus.server_admin_pass');
        if (strlen($server_admin) == 0 || strlen($server_admin_pass) == 0) {
            return response()->json(['result' => false, 'msg' => 'Server Admin credential is not properly defined!']);
        }

        // Get global and template database table names and connection details
        $global_db   = \Config::get('database.connections.globaldb.database');
        $template_db = \Config::get('database.connections.con_template.database');
        $_host = \Config::get('database.connections.con_template.host');
        $_cset = \Config::get('database.connections.con_template.charset');
        $_coll = \Config::get('database.connections.con_template.collation');
        $db_admin_user = \Config::get('database.connections.globaldb.username');
        $db_admin_pass = \Config::get('database.connections.globaldb.password');
        $db_conso_user = \Config::get('database.connections.consodb.username');
        $db_conso_pass = \Config::get('database.connections.consodb.password');

        // Create the database
        DB::statement(DB::raw('CREATE DATABASE ' . $conso_db));

        // Set configuration to use the new database as consodb
        config(['database.connections.consodb.database' => $conso_db]);
        session(['ccp_con_key' => $input['ccp_key']]);
        DB::reconnect('consodb');

        // Create tables in the new database based on the template database
        // Get the table names from the filenames in the template migrations folder
        // We'll use these *IN ORDER* to clone the tables WITH their indeces and constraints
        // from the template database to the newly created one
        $migration_path = base_path() . "/database/migrations/con_template/*.*";
        $table_names = array();
        foreach( glob($migration_path) as $file) {
            $name_beg = strpos($file, "_create_")+8;
            if ($name_beg > 8) {
                $name_end = strpos($file, "_table.") - 1;
                $table_names[] = substr($file, $name_beg, $name_end - $name_beg + 1);
            }
        }

        // Retrieve the CREATE TABLE commands for each table and create them in the new database
        $_qry1 = "SHOW CREATE TABLE ccplus_con_template.ZZZZ";
        $_qry2 = "INSERT INTO ZZZZ SELECT * FROM ccplus_con_template.ZZZZ";
        $ctField = "Create Table";
        foreach ($table_names as $table) {
            // $show_query = preg_replace('/ZZZZ/', $table, $_qry1);
            $show_query = str_replace('ZZZZ', $table, $_qry1);
            $result = DB::select($show_query);
            $raw_command = $result[0]->$ctField;
            $pos = strpos($raw_command, "`");
            if ($pos == false) {
                return response()->json(['result' => false,
                    'msg' => 'Database cloning failed! Cannot build create command to clone ' . $table . ' from template']);
            }
            // Zap any AUTO_INCREMENT value in the create command
            $command = preg_replace("/ AUTO_INCREMENT=(\d+)/", "", $raw_command);
            $data_query = str_replace('ZZZZ', $table, $_qry2);
            // Copy just the ServerAdmin credential from the template.. no other users
            if ($table == "users") {
                $data_query .= " WHERE id=1";
            }
            try {
                DB::connection('consodb')->statement($command);
                DB::connection('consodb')->statement($data_query);
            } catch (\Exception $e) {
                dd($e);
            }
        }

        // Run seeds on the new database
        $exitCode = Artisan::call('db:seed');
        if ($exitCode != 0) {
            return response()->json(['result' => false, 'msg' => 'Database seeding failed with status: ' . $exitCode]);
        }

        // Grants for admin and user access to the new database and tables
        $_grant_Adm  = "GRANT ALL on `" . $conso_db . "`.* TO '" . $db_admin_user . "'@'" . $_host .
                       "' identified by '" . $db_admin_pass . "'";
        DB::statement($_grant_Adm);
        $_grant_Usr = "GRANT SELECT on `" . $conso_db . "`.* TO '" . $db_conso_user . "'@'" . $_host .
                      "' identified by '" . $db_conso_pass . "'";
        DB::statement($_grant_Usr);
        $_grant_Usr = "GRANT UPDATE on `" . $conso_db . "`.users TO '" . $db_conso_user . "'@'" . $_host .
                      "' identified by '" . $db_conso_pass . "'";
        DB::statement($_grant_Usr);

        // Create the new consortium
        $consortium = Consortium::create($request->only('ccp_key', 'name', 'email', 'is_active'));

        // Create the Administrator account in the new consortium users table using input values
        // (The ServerAdmin account and role setting should have copied over from the template database)
        DB::table($conso_db . ".users")->insert([
        ['id' => 2,
         'name' => 'CC-Plus Administrator',
         'password' => Hash::make($input['admin_pass']),
         'email' => $input['admin_user'],
         'inst_id' => 1,
         'is_active' => 1]
        ]);

        // Set role for 'Administrator'
        DB::table($conso_db . ".role_user")->insert(['role_id' =>  99, 'user_id' => 2]);

        return response()->json(['result' => true, 'consortium' => $consortium,
                                 'msg' => 'New consortium : ' . $consortium->name . ' Successfully Created.',
                               ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return JSON
     */
    public function show($id)
    {
        $consortium = Consortium::findOrFail($id); //Find consortium w/ id = $id
        return response()->json(['consortium' => $consortium], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return JSON
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required',
        ]);
        $isActive = 0;
        if ($request->has('is_active')) {
            $isActive = ($request->input('is_active')) ? 1 : 0;
        }
        // Update the entry
        $consortium = Consortium::findOrFail($id);
        $consortium->name = $request->input('name');
        $consortium->email = $request->input('email');
        $consortium->is_active = $isActive;
        $consortium->save();

        // Return the updated object
        return response()->json(['result' => true, 'consortium' => $consortium,
                                 'msg' => 'Consortium : ' . $consortium->name . ' Successfully Updated.',
                               ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $consortium = Consortium::findOrFail($id);
        try {
            $consortium->delete();
            return response()->json(['result' => true, 'msg' => 'Instance successfully deleted']);
        } catch (\Exception $e) {
            return response()->json(['result' => true, 'msg' => $e->getMessage()]);
        }
    }
}
