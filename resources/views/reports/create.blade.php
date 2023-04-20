@extends('layouts.app')
@section('content')
<h3>Create a Report</h3>
<create-report :institutions="{{ json_encode($institutions) }}"
               :inst_groups="{{ json_encode($inst_groups) }}"
               :providers="{{ json_encode($providers) }}"
               :reports="{{ json_encode($reports) }}"
               :fields="{{ json_encode($fields) }}"
></create-report>
@endsection
