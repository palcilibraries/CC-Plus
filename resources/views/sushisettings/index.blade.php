@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      <h3>{{ $conso_name }} : Sushi Settings</h3>
    </div>
  </div>
</div>
<v-app>
  <sushisettings-data-table :institutions="{{ json_encode($institutions) }}"
                         :inst_groups="{{ json_encode($inst_groups) }}"
                         :providers="{{ json_encode($providers) }}"
                         :filters="{{ json_encode($filters) }}"
  ></sushisettings-data-table>
</v-app>
@endsection
