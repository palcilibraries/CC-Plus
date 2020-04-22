@extends('layouts.app')

@section('content')
<div class="col-lg-12 margin-tb">
  <a href="{{ route('admin') }}"><< Back</a>
</div>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>Sushi Settings Summary</h2>
        </div>
    </div>
</div>

<!--
        UNTESTED and NOT connected to the Controller...
-->

<table class="table table-bordered">
  <tr>
     <th>Provider</th>
     <th>Institution</th>
     <th>Customer ID</th>
     <th>Requestor ID</th>
     <th>API Key</th>
  </tr>
  @foreach ($data as $key => $record)
  <tr>
      <td>{{ $record->provider->name }}</td>
      <td>{{ $record->institution->name }}</td>
      <td>{{ $record->customer_id }}</td>
      <td>{{ $record->requestor_id }}</td>
      <td>{{ $record->API_key }}</td>
  </tr>
  @endforeach
</table>

{!! $data->render() !!}
@endsection
