@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h1>{{ $institution->name }}</h1>
        </div>
        <div class="pull-right">
            <a class="btn btn-primary" href="{{ route('institutions.index') }}"> Back</a>
        </div>
    </div>
</div>

<!-- need to pull in and layout sushi_settings?                -->
<!-- maybe a vendor-selector tied to a jquery update           -->
<!-- split-screen vertically? inst-left, sushi details right?  -->

<div class="row details">
    <div class="col-xs-12 col-sm-12 col-md-12">
		<h2>Details</h2>
		<a href="#">edit</a> <em>can we make this swap in the edit view?</em>
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
            <em>what goes here?</em>
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
</div>

<div class="users">
	<h2>Users</h2>
	<a href="#">add new</a>
</div>

<div class="related-list">
	<h2>Providers</h2>
	<a href="#">add new</a>
	
	<div>[connect provider dropdown - shows form when selected]</div>
	
	<div class="provider-list">
		<em>list of connected providers here</em>
	</div>
</div>
@endsection
