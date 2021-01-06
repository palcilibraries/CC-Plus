@extends('layouts.app')

@section('content')
<v-app userform>

	<div class="page-header">
	    <h1>User settings: {{ $user->name }}</h1>
	</div>
    <user-form :user="{{ json_encode($user) }}"
               :all_roles="{{ json_encode($all_roles) }}"
               :institutions="{{ json_encode($institutions) }}"
    ></user-form>

@endsection
