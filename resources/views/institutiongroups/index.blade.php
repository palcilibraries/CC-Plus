@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      <h3>{{ $conso_name }} : Institution Groups</h3>
    </div>
  </div>
</div>
<v-app>
  <institution-groups :groups="{{ json_encode($data) }}"></institution-groups>
</v-app>
@endsection
