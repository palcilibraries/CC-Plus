@extends('layouts.app')
@section('content')
<h1>Create a Report</h1>
<create-report :institutions="{{ json_encode($institutions) }}"
               :inst_groups="{{ json_encode($inst_groups) }}"
               :providers="{{ json_encode($providers) }}"
               :reports="{{ json_encode($reports) }}"
               :fields="{{ json_encode($fields) }}"
               :fy_month="{{ json_encode($fy_month) }}"
></create-report>
@endsection
