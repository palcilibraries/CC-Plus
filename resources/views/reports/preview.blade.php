@extends('layouts.app')

@section('content')
<h3>Usage Report : Preview</h3>
<div id="app">
  <v-app>
    <v-content>
      <report-export :preset_filters="{{ json_encode($preset_filters) }}"
                     :columns="{{ json_encode($columns) }}"
                     :saved_reports="{{ json_encode($saved_reports) }}"
      ></report-export>
    </v-content>
  </v-app>
</div>
@endsection
