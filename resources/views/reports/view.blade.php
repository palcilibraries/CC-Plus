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
@if ( $user_reports->count() )
  <table class="table table-bordered">
    <tr>
      <th>User-Defined Report</th>
      <th>Based-on (Master)</th>
      <th>#-Months</th>
      <th>#-Fields</th>
      <th></th>
    </tr>
    @foreach ($user_reports as $u_report)
      <tr>
        <td><a href="{{ URL::route('savedreports.edit',$u_report->id) }}">{{ $u_report->title }}</a></td>
        <td>{{ $u_report->master->name }}</td>
        <td>{{ $u_report->months }}</td>
        <td>{{ $u_report->field_count }}</td>
        <td><a class="btn btn-success"href="/reports/preview?saved_id={{ $u_report->id }}">Preview</a></td>
      </tr>
    @endforeach
  </table>
@endif
<table class="table table-bordered">
  <tr>
    <th>COUNTER-5 Report</th>
    <th>Description</th>
    <th>Parent</th>
    <th>#-Fields</th>
  </tr>

  @foreach ($master_reports as $report)
    <tr>
      <td><a href="{{ URL::route('reports.show',$report->id) }}">{{ $report->name }} (r{{ $report->revision }})</a></td>
      <td>{{ $report->legend }}</td>
      <td>--Master--</td>
      <td>{{ $report->reportFields->count() }}</td>
    </tr>
    @if ( $report->children->count() )
       @foreach ($report->children as $child)
         <tr>
           <td>&nbsp; &nbsp; <a href="{{ URL::route('reports.show',$child->id) }}">{{ $child->name }} (r{{ $child->revision }})</a></td>
           <td>&nbsp; &nbsp; {{ $child->name }} (r{{ $child->revision }})</td>
           <td>&nbsp; &nbsp; {{ $child->legend }}</td>
           <td>&nbsp; &nbsp; {{ $child->fieldCount() }}</td>
         </tr>
       @endforeach
    @endif
  @endforeach
</table>

@endsection
