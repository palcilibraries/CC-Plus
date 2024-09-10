@extends('layouts.app')
@section('page_title')
    {{ "My Reports -- CC-Plus" }}
@endsection
@section('content')
<home-saved-reports :reports="{{ json_encode($report_data) }}"
                    :counter_reports="{{ json_encode($counter_reports) }}"
                    :myname="{{ json_encode($myname) }}"
></home-saved-reports>
@endsection
