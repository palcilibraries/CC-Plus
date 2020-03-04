@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            @if (auth()->user()->hasRole("Admin"))
            <h2>{{ session('ccp_con_key','') }} : Users</h2>
            @else
            <h2>Users</h2>
            @endif
        </div>
        <div class="pull-right">
            <a class="btn btn-success" href="{{ route('users.create') }}"> Create New User</a>
        </div>
    </div>
</div>
@if ($message = Session::get('success'))
<flash class="alert-flash" message="{{ $message }}"></flash>
@endif
<v-app>
  <user-data-table :users="{{ json_encode($data) }}"></user-data-table>
</v-app>
@endsection
