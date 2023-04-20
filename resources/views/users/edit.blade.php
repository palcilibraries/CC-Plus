@extends('layouts.app')
@section('content')
<h4>Settings for : {{ $user->name }}</h4>
<user-form :user="{{ json_encode($user) }}"
           :all_roles="{{ json_encode($all_roles) }}"
         :institutions="{{ json_encode($institutions) }}"
></user-form>
@endsection
