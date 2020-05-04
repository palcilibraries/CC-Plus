@extends('layouts.app')

@section('content')
<v-app institutiongroupform>
  <institution-group-form :group="{{ json_encode($group) }}"
                          :not_members="{{ json_encode($not_members) }}"
  ></institution-group-form>
</v-app>
@endsection
