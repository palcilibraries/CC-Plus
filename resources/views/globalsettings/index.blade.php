@extends('layouts.app')
@section('content')
<globalsettings :settings="{{ json_encode($settings) }}"></globalsettings>
@endsection
