@extends('layouts.app')

@section('content')
<v-app>
  <serveradmin-dashboard :consortia="{{ json_encode($consortia) }}"></serveradmin-dashboard>
</v-app>
@endsection
