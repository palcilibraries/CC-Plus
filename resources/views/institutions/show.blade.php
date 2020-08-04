@extends('layouts.app')

@section('content')
<v-app institutionform>

	<div class="page-header">
	    <h1>{{ $institution->name }}</h1>
	</div>

    <institution-form :institution="{{ json_encode($institution) }}"
                      :types="{{ json_encode($types) }}"
                      :all_groups="{{ json_encode($all_groups) }}"
    ></institution-form>

    @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
	<users-by-inst :users="{{ json_encode($users) }}"
				   :inst_id="{{ json_encode($institution->id) }}"
				   :all_roles="{{ json_encode($all_roles) }}"
	></users-by-inst>
    @endif
	<div class="related-list">
      <v-expansion-panels><v-expansion-panel>
  	    <v-expansion-panel-header>
	      <h3>Recent Harvest Activity</h3>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
  	      @if (sizeof($harvests) > 0)
			<harvestlog-summary-table :harvests="{{ json_encode($harvests) }}"
									  :inst_id="{{ $institution->id }}"
			></harvestlog-summary-table>
  	      @else
	      <p>No harvest records found for this institution</p>
	      @endif
  	    </v-expansion-panel-content>
	  </v-expansion-panel></v-expansion-panels>
    </div>
    <div class="related-list">
	  <h2 class="section-title">Providers</h2>
	  <all-sushi-by-inst :settings="{{ json_encode($institution->sushiSettings->toArray()) }}"
		  				 :inst_id="{{ json_encode($institution->id) }}"
		  				 :unset="{{ json_encode($unset_providers) }}"
	  ></all-sushi-by-inst>
    </div>

</v-app>
@endsection
