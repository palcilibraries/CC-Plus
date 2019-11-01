@extends('layouts.app')

@section('content')
<div class="col-lg-12 margin-tb">
  <a href="{{ route('admin') }}"><< Back</a>
</div>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>CC+ Report Settings</h2>
        </div>
    </div>
</div>

@if ($message = Session::get('success'))
<flash class="alert-flash" message="{{ $message }}"></flash>
@endif
<table class="table table-bordered">
 <tr>
   <th>Report Name</th>
   <th>Description</th>
   <th>Parent</th>
   <th>#-Fields</th>
 </tr>

 @foreach ($master_reports as $report)
 <tr>
    <td><a href="{{ URL::route('reports.show',$report->id) }}">{{ $report->name }} (r{{ $report->revision }})</a></td>
    <td>{{ $report->legend }}</td>
    <td>--Master--</td>
    <td>{{ $report->reportfields()->count() }}</td>
 </tr>
 @if ( $report->children->count() )
    @foreach ($report->children as $child)
    <tr>
      <td>&nbsp; &nbsp; <a href="{{ URL::route('reports.show',$child->id) }}">{{ $child->name }} (r{{ $child->revision }})</a></td>
      <td>&nbsp; &nbsp; {{ $child->name }} (r{{ $child->revision }})</td>
      <td>&nbsp; &nbsp; {{ $child->legend }}</td>
      <td>&nbsp; &nbsp; {{ $report->name }}</td>
      <td>&nbsp; &nbsp; {{ $child->reportfields()->count() }}</td>
    </tr>
    @endforeach
 @endif
 @endforeach
</table>

@endsection
