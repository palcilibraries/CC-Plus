@extends('layouts.app')
@section('content')
<div class="d-flex pl-2">
  @if (auth()->user()->hasRole("Admin"))
    <h3>{{ $conso_name }} : Users</h3>
  @else
    <h3>Users</h3>
  @endif
</div>
<user-data-table :users="{{ json_encode($data) }}"
                 :institutions="{{ json_encode($institutions) }}"
                 :allowed_roles="{{ json_encode($allowed_roles) }}"
                 :all_groups="{{ json_encode($all_groups) }}"
                 :filters="{{ json_encode($filters) }}"
></user-data-table>
@endsection
