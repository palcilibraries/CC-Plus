@extends('layouts.app')
@section('content')
<globaladmin-dashboard :consortia="{{ json_encode($consortia) }}"></globaladmin-dashboard>
@endsection
