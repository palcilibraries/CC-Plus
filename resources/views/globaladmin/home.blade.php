@extends('layouts.app')

@section('content')
<v-app>
  <globaladmin-dashboard :consortia="{{ json_encode($consortia) }}"></globaladmin-dashboard>
</v-app>
@endsection
