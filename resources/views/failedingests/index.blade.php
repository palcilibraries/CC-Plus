@extends('layouts.app')

@section('content')
<div class="col-lg-12 margin-tb">
  <a href="{{ route('admin') }}"><< Back</a>
</div>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>Failed Ingests Summary</h2>
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
     <th>Retry Count</th>
     <th>RunDate</th>
  </tr>
  @foreach ($data as $key => $record)
  <tr>
      <td>{{ $record->sushiSetting->provider->name }}</td>
      <td>{{ $record->sushiSetting->institution->name }}</td>
      <td>{{ $record->report->name }}</td>
      <td>{{ $record->yearmon }}</td>
      <td>{{ $record->process_step }}</td>
      <td>{{ $record->retry_count }}</td>
      <td><a href="{{ route('failedingests.show',$record->id) }}">{{ $record->updated_at }}</a></td>
  </tr>
  @endforeach
</table>

{!! $data->render() !!}
@endsection
