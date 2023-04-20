@extends('layouts.app')
@section('content')
<h3>{{ $conso_name }} : Institution Groups</h3>
<institution-groups :groups="{{ json_encode($data) }}"></institution-groups>
@endsection
