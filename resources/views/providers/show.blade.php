@extends('layouts.app')

@section('content')
<v-app providerform>
    <provider-form :provider="{{ json_encode($provider) }}"
	     		   :institutions="{{ json_encode($institutions) }}"
	 	      	   :master_reports="{{ json_encode($master_reports) }}"
    ></provider-form>
	<div class="related-list">
      <v-expansion-panels><v-expansion-panel>
  	    <v-expansion-panel-header>
	      <h3>Recent Harvest Activity</h3>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
  	      @if (sizeof($harvests) > 0)
    	    <harvestlog-summary-table :harvests="{{ json_encode($harvests) }}"
									  :prov_id="{{ $provider->id }}"
			></harvestlog-summary-table>
  	      @else
	      <p>No harvest records found for this provider</p>
	      @endif
  	    </v-expansion-panel-content>
	  </v-expansion-panel></v-expansion-panels>
    </div>
	<div class="related-list">
      <h2 class="section-title">Institutions</h2> @if (auth()->user()->hasRole("Admin"))<a href="/institutions/create" class="section-action">add new</a> @endif
      <all-sushi-by-prov :settings="{{ json_encode($provider->sushiSettings) }}"
	                     :prov_id="{{ json_encode($provider->id) }}"
                         :unset="{{ json_encode($unset_institutions) }}"
	  ></all-sushi-by-prov>
    </div>
  </div>

</v-app>
@endsection
