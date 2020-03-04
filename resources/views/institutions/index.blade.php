@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      <h2>{{ session('ccp_con_key','') }} : Institutions</h2>
    </div>
    <div class="pull-right">
      <a class="btn btn-success" href="{{ route('institutions.create') }}">Create New Institution</a>
    </div>
  </div>
</div>
@if ($message = Session::get('success'))
<flash class="alert-flash" message="{{ $message }}"></flash>
@endif
<v-app>
  <institution-data-table :institutions="{{ json_encode($data) }}"></institution-data-table>
</v-app>
@endsection
