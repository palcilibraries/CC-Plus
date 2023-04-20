@extends('layouts.app')
@section('content')
<h3>Usage Report : Preview</h3>
@if ($title != "")
  <h5>{{ $title }}</h5>
@endif
<report-preview :preset_filters="{{ json_encode($preset_filters) }}"
                :columns="{{ json_encode($columns) }}"
                :fields="{{ json_encode($fields) }}"
                :saved_reports="{{ json_encode($saved_reports) }}"
                :filter_options="{{ json_encode($filter_options) }}"
                :rangetype="{{ json_encode($rangetype) }}"
></report-preview>
@endsection
