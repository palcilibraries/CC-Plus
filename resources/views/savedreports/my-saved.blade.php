@extends('layouts.app')
@section('content')
<home-saved-reports :reports="{{ json_encode($report_data) }}"
                    :counter_reports="{{ json_encode($counter_reports) }}"
></home-saved-reports>
@endsection
