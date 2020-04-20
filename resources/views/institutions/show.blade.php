@extends('layouts.app')

@section('content')
<v-app institutionform>

<div>
	<div class="page-header">
	    <h1>{{ $institution->name }}</h1>
	</div>
	@if ( auth()->user()->hasAnyRole(['Admin']) )
	<div class="page-action">
		<a class="btn btn-primary v-btn v-btn--contained btn-danger theme--light v-size--small" href="#">Delete</a>
	</div>
	@endif
</div>

    <institution-form :institution="{{ json_encode($institution) }}"
                      :types="{{ json_encode($types) }}"
                      :inst_groups="{{ json_encode($inst_groups) }}"
                      :all_groups="{{ json_encode($all_groups) }}"
    ></institution-form>

  @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
    <div class="users">
	<h2 class="section-title">Users</h2>
    <v-btn small color="primary" type="button" href="{{ route('users.create') }}" class="section-action">add new</v-btn>
    <users-by-inst :users="{{ json_encode($users) }}"></users-by-inst>
	</div>
  @endif
  <hr>
  <div class="related-list">
	  <h2 class="section-title">Providers</h2>
	  <v-btn small color="primary" type="button" href="{{ route('providers.create') }}" class="section-action">add new</v-btn>
	  <all-sushi-by-inst :settings="{{ json_encode($institution->sushiSettings->toArray()) }}"
		  				 :inst_id="{{ json_encode($institution->id) }}"
		  				 :unset="{{ json_encode($unset_providers) }}"
	  ></all-sushi-by-inst>
  </div>

</v-app>


@endsection
