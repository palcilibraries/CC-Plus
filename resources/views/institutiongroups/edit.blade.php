@extends('layouts.app')
@section('content')
<institution-group-form :group="{{ json_encode($group) }}"
                        :not_members="{{ json_encode($not_members) }}"
></institution-group-form>
@endsection
