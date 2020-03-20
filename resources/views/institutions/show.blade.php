@extends('layouts.app')

@section('content')
<v-app institutionform>

<div>
	<div class="page-header">
	    <h1>{{ $institution->name }}</h1>
	</div>
	<div class="page-action">
		<a class="btn btn-primary" href="#">Delete</a>
	</div>
</div>

  <div class="details">
	<h2 class="section-title">Details</h2>
	<a href="{{ route('institutions.edit',$institution->id) }}">Edit<a/>
	<!-- <a href="#" class="section-action">edit</a> <em>can we make this swap in the edit view?</em> -->
	<div class="form-group">
	    <strong>Type:</strong>
	    {{ $institution->institutiontype->name }}
	</div>
	<div class="form-group">
	  <strong>Groups:</strong>
	  @foreach($inst_groups as $group_id => $group_name)
	     @if($institution->isAMemberof($group_id))
	        <label class="badge badge-success">{{ $group_name }} </label>
	     @endif
	  @endforeach
	</div>
	<div class="form-group">
	    <strong>FTE:</strong>
	    {{ $institution->fte }}
	</div>
	<div class="form-group">
	    <strong>Visibility:</strong>
	    <em>placeholder</em>
	</div>
	<div class="form-group">
	    <strong>Status:</strong>
	    {{ $institution->is_active ? 'Active' : 'Inactive' }}
	</div>
	<div class="form-group">
	    <strong>Notes:</strong>
	    {{ $institution->notes }}
	</div>
  </div>
  @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
    <div class="users">
	<h2 class="section-title">Users</h2>
    <v-btn small color="primary" type="button" href="{{ route('users.create') }}" class="section-action">add new</v-btn>
	<users-by-inst :users="{{ json_encode($institution->users) }}"></users-by-inst>
  @endif
  <hr>
  <div class="related-list">
	  <h2 class="section-title">Providers</h2>
	  <v-btn small color="primary" type="button" href="{{ route('providers.create') }}" class="section-action">add new</v-btn>
	  <all-sushi-by-inst :settings="{{ json_encode($institution->sushiSettings->toArray()) }}"
		  				 :unset="{{ json_encode($unset_providers) }}"
	  ></all-sushi-by-inst>
  </div>

</v-app>


@endsection
