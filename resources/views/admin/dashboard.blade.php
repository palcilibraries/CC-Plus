@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Admin Dashboard</div>
<h1>HI SCOTT!</h1>
                <div class="panel-body">
                    @if (session('status'))
                        <div class="alert alert-success">
                            {{ session('status') }}
                        </div>
                    @endif

                    This is the Admin Dashboard. You must be privileged to be here !
                    <p>&nbsp;</p>
                    <h3>Things you can do from here</h3>
                    <ul>
                      <li><a href="/users">Manage Users</a> (<a href="/roles">Roles</a>)</li>
                      <li><a href="/institutions">Institution Management</a></li>
                      <ul>
                        <li><a href="/institutiontypes">Types</a></li>
                        <li><a href="/institutiongroups">Groups</a></li>
                      </ul>
                      <li><a href="/providers">Provider Management</a></li>
                      <li><a href="/harvestlogs">Harvest Logs</a></li>
                      <ul>
                        <li><a href="/failedharvests">Failed Harvests</a></li>
                      </ul>
                      <li><a href="/alerts">Alerts</a></li>
                      <ul>
                        <li><a href="/alertsettings">Alert Settings</a></li>
                      </ul>
                      <li><a href="/reports">Reports & Fields</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
