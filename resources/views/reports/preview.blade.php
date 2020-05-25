@extends('layouts.app')

@section('content')
<h3>Usage Report : Preview</h3>
@if ($title != "")
<h5>{{ $title }}</h5>
@endif
<div id="app">
  <v-app>
    <v-content>
      <report-preview :preset_filters="{{ json_encode($preset_filters) }}"
                      :fields="{{ json_encode($fields) }}"
                      :columns="{{ json_encode($columns) }}"
                      :saved_reports="{{ json_encode($saved_reports) }}"
      ></report-preview>
    </v-content>
  </v-app>
</div>
@endsection
