@extends('layouts.app')

@section('content')
<h3> Usage Report</h3>
<v-app>
  <div>
      <!-- SideNav/Filters/etc. -->
      <!--
         The filtering vue components need to be able up affect the choices available in the
         other filters when selections are made. This might mean keeping all potential choices
         in the Vuex state variable (yikes!? size!?) and then tying these state variables to
         the select dropdowns.
         The Publisher list is... BiG ... for the limited # of test providers we have so far...
      -->
      <!--
        These should be "embed-able" or "include-able" into a vuetify navigation drawer?
        Probably need to push these down into the ReportDataTable compenents anyway...
<inst-group-filter :institutiongroups="{{ json_encode($inst_groups) }}"></inst-group-filter>
      -->
      <title-report></title-report>
  </div>
  <div>
      <!-- report-data-table -->
      <!--
        on mounted(), need to call a "data-refresh" call to build initial display?
        Vue component will handle the data-table rendering and calling for data-refresh
        if any of the filters/facets are changed
      -->
  </div>
</v-app>
@if ( Auth::user()->hasRole("Admin") )
    I See You're an Admin!
@elseif ( Auth::user()->hasRole("Manager") )
    I See You're a Manager!
@elseif ( Auth::user()->hasRole("Viewer") )
    I See You're a Viewer!
@else
    You are logged in as a user.
@endif

@endsection
