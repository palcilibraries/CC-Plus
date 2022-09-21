@extends('layouts.app')

@section('content')
<v-app>
  <globalsettings :settings="{{ json_encode($settings) }}"
  ></globalsettings>
</v-app>
@endsection
