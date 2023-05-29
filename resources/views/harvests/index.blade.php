@extends('layouts.app')
@section('content')
<harvesting :harvests="{{ json_encode($harvests) }}"
            :institutions="{{ json_encode($institutions) }}"
            :groups="{{ json_encode($groups) }}"
            :providers="{{ json_encode($providers) }}"
            :reports="{{ json_encode($reports) }}"
            :bounds="{{ json_encode($bounds) }}"
            :filters="{{ json_encode($filters) }}"
></harvesting>
@endsection
