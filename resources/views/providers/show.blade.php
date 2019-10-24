@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2> Show Providers</h2>
        </div>
        <div class="pull-right">
            <a class="btn btn-primary" href="{{ route('providers.index') }}"> Back</a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Name:</strong>
            {{ $provider->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Status:</strong>
            {{ $provider->active ? 'Active' : 'Inactive' }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <?php
            $inst_name = ($provider->institution->id == 1) ? "Entire Consortium" : $provider->institution->name;
            ?>
            <strong>Serves:</strong> {{ $inst_name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Service URL:</strong> {{ $provider->server_url_r5 }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Security:</strong> {{ $provider->security }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Username:</strong> {{ $provider->auth_username }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Password:</strong> {{ $provider->auth_password }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Harvests on Day:</strong> {{ $provider->day_of_month }}
        </div>
    </div>
</div>
@endsection
