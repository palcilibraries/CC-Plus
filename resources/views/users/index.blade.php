@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            @if (auth()->user()->hasRole("Admin"))
            <h3>{{ session('ccp_con_key','') }} : Users</h3>
            @else
            <h3>Users</h3>
            @endif
        </div>
    </div>
</div>
<v-app>
  <user-data-table :users="{{ json_encode($data) }}"
                   :institutions="{{ json_encode($institutions) }}"
                   :allowed_roles="{{ json_encode($allowed_roles) }}"
                   :all_groups="{{ json_encode($all_groups) }}"
                   :filters="{{ json_encode($filters) }}"
  ></user-data-table>
</v-app>
@endsection
