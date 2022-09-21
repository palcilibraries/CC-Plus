@extends('layouts.app')

@section('content')
<v-app>
  <superuser-dashboard :consortia="{{ json_encode($consortia) }}"></superuser-dashboard>
</v-app>
@endsection
