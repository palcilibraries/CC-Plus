@extends('layouts.app')
@section('content')
<h1>{{ $conso_name }} : Institution Groups</h1>
<institution-groups :groups="{{ json_encode($data) }}"></institution-groups>
@endsection
