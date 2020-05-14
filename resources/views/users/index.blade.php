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
    </div>
</div>
<v-app>
  <user-data-table :users="{{ json_encode($data) }}"
                   :institutions="{{ json_encode($institutions) }}"
                   :all_roles="{{ json_encode($all_roles) }}"
  ></user-data-table>
</v-app>
@endsection
