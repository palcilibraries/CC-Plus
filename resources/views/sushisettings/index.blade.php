@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      <h3>{{ session('ccp_con_key','') }} : Sushi Settings</h3>
    </div>
  </div>
</div>
<v-app>
  <sushisettings-data-table :all_connectors="{{ json_encode($all_connectors) }}"
                         :institutions="{{ json_encode($institutions) }}"
                         :providers="{{ json_encode($providers) }}"
                         :filters="{{ json_encode($filters) }}"
  ></sushisettings-data-table>
</v-app>
@endsection
