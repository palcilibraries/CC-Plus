@extends('layouts.app')
@section('content')
<div class="d-flex pl-2">
  <h3>{{ $conso_name }} : Sushi Settings</h3>
</div>
<sushisettings-data-table :institutions="{{ json_encode($institutions) }}"
                          :inst_groups="{{ json_encode($inst_groups) }}"
                          :providers="{{ json_encode($providers) }}"
                          :filters="{{ json_encode($filters) }}"
></sushisettings-data-table>
@endsection
