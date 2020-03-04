@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      @if (auth()->user()->hasRole("Admin"))
      <h2>{{ session('ccp_con_key','') }} : Providers</h2>
      @else
      <h2>Providers</h2>
      @endif
    </div>
    @if (auth()->user()->hasAnyRole(['Admin','Manager']))
    <div class="pull-right">
      <a class="btn btn-success" href="{{ route('providers.create') }}"> Create New Provider</a>
    </div>
    @endif
  </div>
</div>
@if ($message = Session::get('success'))
<flash class="alert-flash" message="{{ $message }}"></flash>
@endif
<v-app>
  <provider-data-table :providers="{{ json_encode($data) }}"></provider-data-table>
</v-app>
@endsection
