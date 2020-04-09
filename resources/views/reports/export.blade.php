@extends('layouts.app')

@section('content')
<h3>Usage Report : Export</h3>
<div id="app">
  <v-app>
    <v-content>
      <!-- Testing - need to pass lastym in as a variable... -->
      <date-range :lastym="{{ json_encode('2020-01') }}"></date-range>
      <title-report-export></title-report-export>
    </v-content>
  </v-app>
</div>
@endsection
