@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      <h3>{{ session('ccp_con_key','') }} : Institutions</h3>
    </div>
  </div>
</div>
<v-app>
  <institution-data-table :all_groups="{{ json_encode($all_groups) }}"
                          :filters="{{ json_encode($filters) }}"
  ></institution-data-table>
</v-app>
@endsection
