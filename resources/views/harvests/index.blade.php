@extends('layouts.app')
@section('page_title')
    {{ "Harvesting -- CC-Plus" }}
@endsection
@section('content')
<harvesting :harvests="{{ json_encode($harvests) }}"
            :institutions="{{ json_encode($institutions) }}"
            :groups="{{ json_encode($groups) }}"
            :providers="{{ json_encode($providers) }}"
            :reports="{{ json_encode($reports) }}"
            :bounds="{{ json_encode($bounds) }}"
            :filters="{{ json_encode($filters) }}"
            :codes="{{ json_encode($codes) }}"
            :presets="{{ json_encode($presets) }}"
            :conso="{{ json_encode($conso) }}"
></harvesting>
@endsection
