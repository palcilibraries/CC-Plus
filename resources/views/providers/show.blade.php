@extends('layouts.app')

@section('content')
<v-app providerform>

<div>
	<div class="page-header">
	    <h1>{{ $provider->name }}</h1>
	</div>
	@if ( auth()->user()->hasAnyRole(['Admin']) )
	<div class="page-action">
		<a class="btn btn-primary v-btn v-btn--contained btn-danger theme--light v-size--small" href="#">Delete</a>
	</div>
	@endif
</div>

<provider-form :provider="{{ json_encode($_prov) }}"
	 :prov_inst_name="{{ json_encode($provider->institution->name) }}"
	 :institutions="{{ json_encode($institutions) }}"
	 :master_reports="{{ json_encode($master_reports) }}"
	 :provider_reports="{{ json_encode($provider_reports) }}"
></provider-form>

<!-- ******** create all-sushi-by-prov  ******** -->

  <div class="related-list">
	  <h2 class="section-title">Institutions</h2>
	  <v-btn small color="primary" type="button" href="{{ route('institutions.create') }}" class="section-action">add new</v-btn>
	  <all-sushi-by-prov :settings="{{ json_encode($provider->sushiSettings->toArray()) }}"
		  				 :prov_id="{{ json_encode($provider->id) }}"
		  				 :unset="{{ json_encode($unset_institutions) }}"
	  ></all-sushi-by-prov>
  </div>

</v-app>

@endsection
