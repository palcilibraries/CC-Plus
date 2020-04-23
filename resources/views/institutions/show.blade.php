@extends('layouts.app')

@section('content')
<v-app institutionform>

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
  <div class="related-list">
	  <hr>
	  <h2 class="section-title">Providers</h2>
	  <v-btn small color="primary" type="button" href="{{ route('providers.create') }}" class="section-action">add new</v-btn>
	  <all-sushi-by-inst :settings="{{ json_encode($institution->sushiSettings->toArray()) }}"
		  				 :inst_id="{{ json_encode($institution->id) }}"
		  				 :unset="{{ json_encode($unset_providers) }}"
	  ></all-sushi-by-inst>
  </div>

</v-app>


@endsection
