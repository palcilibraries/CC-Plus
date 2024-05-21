@extends('layouts.app')
@section('page_title')
    {{ "Report Preview -- CC-Plus" }}
@endsection
@section('content')
<h1>Usage Report : Preview</h1>
@if ($title != "")
  <h3>{{ $title }}</h3>
@endif
<report-preview :preset_filters="{{ json_encode($preset_filters) }}"
                :columns="{{ json_encode($columns) }}"
                :fields="{{ json_encode($fields) }}"
                :saved_reports="{{ json_encode($saved_reports) }}"
                :filter_options="{{ json_encode($filter_options) }}"
                :rangetype="{{ json_encode($rangetype) }}"
></report-preview>
@endsection
