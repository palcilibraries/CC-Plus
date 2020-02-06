@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>Failed Ingest Details</h2>
        </div>
        <div class="pull-right">
            <a class="btn btn-primary" href="{{ route('failedingests.index') }}"> Back</a>
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
            <strong>Provider: </strong>
            {{ $record->ingest->sushiSetting->provider->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Institution: </strong>
            {{ $record->ingest->sushiSetting->institution->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Report: </strong>
            {{ $record->ingest->report->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Data for Month: </strong>
            {{ $record->ingest->yearmon }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Processing Step: </strong>
            {{ $record->process_step }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Detail: </strong><br />
            (Error: {{ $record->ccplusError->id }}) : {{ $record->ccplusError->message }} <br />
            {{ $record->detail }}
        </div>
    </div>
</div>

{!! Form::close() !!}
@endsection
