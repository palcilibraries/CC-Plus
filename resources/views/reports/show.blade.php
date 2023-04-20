@extends('layouts.app')
@section('content')
<show-counter-report :report="{{ json_encode($report) }}"
                     :fields="{{ json_encode($fields) }}"
                     :filters="{{ json_encode($filters) }}"
></show-counter-report>
@endsection
