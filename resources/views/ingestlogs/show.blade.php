@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>Ingest Record Details</h2>
        </div>
        <div class="pull-right">
            <a class="btn btn-primary" href="{{ route('ingestlogs.index') }}"> Back</a>
        </div>
    </div>
</div>

@if (count($errors) > 0)
    <div class="alert alert-danger">
        <strong>Whoops!</strong> There were some problems with your input.<br><br>
        <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Institution: </strong>
            {{ $record->sushisetting->institution->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Provider: </strong>
            {{ $record->sushisetting->provider->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Report: </strong>
            {{ $record->report->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Issue: </strong>
            @if ( isset($failed['detail']))
               {{ $failed['detail'] }}
            @else
               No error message retained
            @endif
            <br />
            <a href="{{ route('failedingests.show',$failed['id']) }}">(Failed Ingest Detail)</a>
        </div>
    </div>
</div>

{!! Form::close() !!}
@endsection
