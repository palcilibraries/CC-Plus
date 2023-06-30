@extends('layouts.app')
@section('content')
<div class="d-flex pl-2"><h1>{{ $conso_name }} : Institutions</h1></div>
<institution-data-table :all_groups="{{ json_encode($all_groups) }}"
                        :filters="{{ json_encode($filters) }}"
></institution-data-table>
@endsection
