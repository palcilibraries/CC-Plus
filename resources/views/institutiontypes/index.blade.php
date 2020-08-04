@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      <h2>{{ session('ccp_con_key','') }} : Institution Types</h2>
    </div>
  </div>
</div>
<v-app>
  <institution-types :types="{{ json_encode($data) }}"></institution-types>
</v-app>
@endsection
