@extends('layouts.app')

@section('content')
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">Admin Dashboard</div>
        <div class="card-body">
          @if (session('status'))
          <div class="alert alert-success" role="alert">
            {{ session('status') }}
          </div>
          @endif
          <p align='center'>
            This dashboard is linked to the topnav for admins and managers.<br />
            No distinction is being made about whether a manager has use for or should
            see or have access to the content/resources linked to below. This is just a
            temporary holding place that still needs work.
          </p>
          <p>
            The source file is kept in .../views/admin/dashboard, and is a place for
            Administrative links/resources/etc. This page not be necessary, however,
            if we decide to relocate the functions below to other places or decide we
            don't need UI-based access to them.
          </p>
          <ul>
            <li><a href="/institutiontypes">Types</a></li>
            <li><a href="/institutiongroups">Groups</a></li>
            <li><a href="/failedharvests">Failed Harvests</a>  (this belongs in the Harvests view)</li>
            <li><a href="/alertsettings">Alert Settings</a>  (this belongs with the Alerts view)</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
