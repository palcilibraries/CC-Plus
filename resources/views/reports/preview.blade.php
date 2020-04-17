@extends('layouts.app')

@section('content')
<h3>Usage Report : Preview</h3>
<div id="app">
  <v-app>
    <v-content>
<? dd($filters); ?>
      <title-report-export :input_filters="{{ json_encode($filters) }}"></title-report-export>
    </v-content>
  </v-app>
</div>
@endsection
