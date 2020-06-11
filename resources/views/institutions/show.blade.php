@extends('layouts.app')

@section('content')
<v-app institutionform>

	<div class="page-header">
	    <h1>{{ $institution->name }}</h1>
	</div>

  <institution-form :institution="{{ json_encode($institution) }}"
                    :types="{{ json_encode($types) }}"
                    :inst_groups="{{ json_encode($inst_groups) }}"
                    :all_groups="{{ json_encode($all_groups) }}"
  ></institution-form>

  @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
    <div class="users">
	<h2 class="section-title">Users</h2>
    <users-by-inst :users="{{ json_encode($users) }}"
				   :inst_id="{{ json_encode($institution->id) }}"
				   :all_roles="{{ json_encode($all_roles) }}"
	></users-by-inst>
	</div>
  @endif
  <div class="related-list">
	  <hr>
	  <h2 class="section-title">Providers</h2>
	  @if ( auth()->user()->hasRole('Admin') )
	  <v-btn small color="primary" type="button" href="{{ route('providers.create') }}" class="section-action">add new</v-btn>
	  @else
	  	@if (auth()->user()->hasRole('Manager'))
		<v-row>
      	  <v-col cols="2"><h5>Export settings to:</h5></v-col>
      	  <v-col>
  	 	    <a :href="'/institutions/export/xls'">.xls</a> &nbsp; &nbsp;
  		    <a :href="'/institutions/export/xlsx'">.xlsx</a>
  		  </v-col>
  	    </v-row>
		@endif
	  @endif
	  <all-sushi-by-inst :settings="{{ json_encode($institution->sushiSettings->toArray()) }}"
		  				 :inst_id="{{ json_encode($institution->id) }}"
		  				 :unset="{{ json_encode($unset_providers) }}"
	  ></all-sushi-by-inst>
  </div>

</v-app>


@endsection
