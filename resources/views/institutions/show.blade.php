@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="page-header">
            <h1>{{ $institution->name }}</h1>
        </div>
        <div class="page-action">
<!--            <a class="btn btn-primary" href="{{ route('institutions.index') }}"> Back</a>-->
			<a class="btn btn-primary" href="#">Delete</a>
        </div>
    </div>
</div>

<!-- need to pull in and layout sushi_settings?                -->
<!-- maybe a vendor-selector tied to a jquery update           -->
<!-- split-screen vertically? inst-left, sushi details right?  -->

<div class="details">
	<h2 class="section-title">Details</h2>
	<a href="#" class="section-action">edit</a> <em>can we make this swap in the edit view?</em>
	<div class="form-group">
	    <strong>Type:</strong>
	    {{ $institution->institutiontype->name }}
	</div>
	<div class="form-group">
	  <strong>Groups:</strong>
	  @foreach($groups as $group_id => $group_name)
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

<div class="users">
	<h2 class="section-title">Users</h2>
	<a href="#" class="section-action">add new</a>
	<em>associated user list here</em>
</div>

<div class="related-list">
	<h2 class="section-title">Providers</h2>
	<a href="#" class="section-action">add new</a>
	
	<div>[connect provider dropdown - shows form when selected]</div>
	
	<div class="provider-list">
		<em>list of connected providers here</em>
	</div>
</div>
@endsection
