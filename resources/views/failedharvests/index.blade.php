@extends('layouts.app')

@section('content')
<div class="col-lg-12 margin-tb">
  <a href="{{ route('admin') }}"><< Back</a>
</div>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>Failed Harvests Summary</h2>
        </div>
    </div>
</div>

@if ($message = Session::get('success'))
<flash class="alert-flash" message="{{ $message }}"></flash>
@endif

<table class="table table-bordered">
  <tr>
     <th>Provider</th>
     <th>Institution</th>
     <th>Report</th>
     <th>Usage Date</th>
     <th>Process Step</th>
     <th>Severity</th>
     <th>RunDate</th>
  </tr>
  @foreach ($data as $key => $record)
  <tr>
      <td>{{ $record->harvest->sushiSetting->provider->name }}</td>
      <td>{{ $record->harvest->sushiSetting->institution->name }}</td>
      <td>{{ $record->harvest->report->name }}</td>
      <td>{{ $record->harvest->yearmon }}</td>
      <td>{{ $record->process_step }}</td>
      <td>{{ $record->ccplusError->severity }}</td>
      <td><a href="{{ route('failedharvests.show',$record->id) }}">{{ $record->created_at }}</a></td>
  </tr>
  @endforeach
</table>

{!! $data->render() !!}
@endsection
