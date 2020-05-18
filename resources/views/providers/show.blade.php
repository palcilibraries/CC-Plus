@extends('layouts.app')

@section('content')
<v-app providerform>

	<div class="page-header">
	    <h1>{{ $provider->name }}</h1>
	</div>

    <provider-form :provider="{{ json_encode($provider) }}"
	     		   :institutions="{{ json_encode($institutions) }}"
	 	      	   :master_reports="{{ json_encode($master_reports) }}"
    ></provider-form>

    <div class="related-list">
	  <h2 class="section-title">Institutional Sushi Settings</h2>
	  @if (auth()->user()->hasRole("Admin"))
	  <v-btn small color="primary" type="button" href="{{ route('institutions.create') }}" class="section-action">
		  Add new institution
	  </v-btn>
	  @endif
	  <all-sushi-by-prov :settings="{{ json_encode($provider->sushiSettings->toArray()) }}"
		  				 :prov_id="{{ json_encode($provider->id) }}"
		  				 :unset="{{ json_encode($unset_institutions) }}"
	  ></all-sushi-by-prov>
    </div>

</v-app>

@endsection
