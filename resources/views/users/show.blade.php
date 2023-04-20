@extends('layouts.app')
@section('content')
<h1>User settings: {{ $user->name }}</h1>
<user-form :user="{{ json_encode($user) }}"
           :all_roles="{{ json_encode($all_roles) }}"
           :institutions="{{ json_encode($institutions) }}"
></user-form>
@endsection
