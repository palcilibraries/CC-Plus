@extends('layouts.app')
@section('content')

@if (auth()->user()->hasRole("Admin"))
  <h1>{{ session('ccp_con_key','') }} : {{ $provider['name'] }}</h1>
@else
  <h1>{{ $provider['name'] }}</h1>
@endif
<provider-form :provider="{{ json_encode($provider) }}"
               :institutions="{{ json_encode($institutions) }}"
               :unset="{{ json_encode($unset_institutions) }}"
               :master_reports="{{ json_encode($master_reports) }}"
               :connectors="{{ json_encode($connectors) }}"
               :harvests="{{ json_encode($harvests) }}"
></provider-form>
@endsection
