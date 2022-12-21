@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      @if (auth()->user()->hasRole("Admin"))
      <h3>{{ session('ccp_con_key','') }} : {{ $provider['name'] }}</h3>
      @else
      <h3>{{ $provider['name'] }}</h3>
      @endif
    </div>
  </div>
</div>
<v-app providerform>
    <provider-form :provider="{{ json_encode($provider) }}"
                   :institutions="{{ json_encode($institutions) }}"
                   :unset="{{ json_encode($unset_institutions) }}"
                   :master_reports="{{ json_encode($master_reports) }}"
                   :connectors="{{ json_encode($connectors) }}"
                   :harvests="{{ json_encode($harvests) }}"
    ></provider-form>
</v-app>
@endsection
