@extends('layouts.app')
@section('content')
<failed-harvests :failed_harvests="{{ json_encode($failed) }}"
                 :institutions="{{ json_encode($institutions) }}"
                 :providers="{{ json_encode($providers) }}"
                 :reports="{{ json_encode($reports) }}"
                 :bounds="{{ json_encode($bounds) }}"
                 :header="{{ json_encode($header) }}"
                 :filterable=1
></failed-harvests>
@endsection
