@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>Harvest Record Details</h2>
        </div>
        <div class="pull-right">
            <a class="btn btn-primary" href="{{ route('harvestlogs.index') }}"> Back</a>
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
            {{ $record->sushiSetting->institution->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Provider: </strong>
            {{ $record->sushiSetting->provider->name }}
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
            @if ($record->failedharvests()->count() > 0)
            <strong>Failed attempts: </strong>
            <table class="table table-bordered">
              <tr>
                 <th>Attempted</th>
                 <th>Process Step</th>
                 <th>Error Code</th>
                 <th>Severity</th>
                 <th>Message</th>
                 <th>Details</th>
              </tr>
              @foreach ($record->failedharvests as $fail)
              <tr>
                  <td>{{ $fail->created_at }}</td>
                  <td>{{ $fail->process_step }}</td>
                  <td>{{ $fail->error_id }}</td>
                  <td>{{ $fail->ccplusError->severity }}</td>
                  <td>{{ $fail->ccplusError->message }}</td>
                  <td>{{ $fail->detail }}</td>
              </tr>
              @endforeach
            </table>
            @endif
        </div>
    </div>
</div>

{!! Form::close() !!}
@endsection
