@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2> Show Institutions</h2>
        </div>
        <div class="pull-right">
            <a class="btn btn-primary" href="{{ route('institutions.index') }}"> Back</a>
        </div>
    </div>
</div>

<!-- need to pull in and layout sushi_settings?                -->
<!-- maybe a vendor-selector tied to a jquery update           -->
<!-- split-screen vertically? inst-left, sushi details right?  -->

<div class="row">
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Name:</strong>
            {{ $institution->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Type:</strong>
            {{ $institution->institutiontype->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Status:</strong>
            {{ $institution->is_active ? 'Active' : 'Inactive' }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>FTE:</strong>
            {{ $institution->fte }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Notes:</strong>
            {{ $institution->notes }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
          <strong>Included in Groups:</strong>
          @foreach($groups as $group_id => $group_name)
             @if($institution->isAMemberof($group_id))
                <label class="badge badge-success">{{ $group_name }} </label>
             @endif
          @endforeach
        </div>
    </div>
</div>
@endsection
