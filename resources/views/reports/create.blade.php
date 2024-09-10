@extends('layouts.app')
@section('page_title')
    {{ "Create Report -- CC-Plus" }}
@endsection
@section('content')
<create-report :institutions="{{ json_encode($institutions) }}"
               :inst_groups="{{ json_encode($inst_groups) }}"
               :providers="{{ json_encode($providers) }}"
               :reports="{{ json_encode($reports) }}"
               :fields="{{ json_encode($fields) }}"
               :fy_month="{{ json_encode($fy_month) }}"
               :conso="{{ json_encode($conso) }}"
></create-report>
@endsection
