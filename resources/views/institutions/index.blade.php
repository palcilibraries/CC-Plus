@extends('layouts.app')
@section('content')
<div class="d-flex pl-2"><h3>{{ $conso_name }} : Institutions</h3></div>
<institution-data-table :all_groups="{{ json_encode($all_groups) }}"
                        :filters="{{ json_encode($filters) }}"
></institution-data-table>
@endsection
