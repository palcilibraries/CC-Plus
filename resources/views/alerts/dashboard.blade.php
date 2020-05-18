@extends('layouts.app')

@section('content')
<div class="col-lg-12 margin-tb">
  <a href="{{ route('admin') }}"><< Back</a>
</div>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>CC+ System Alerts</h2>
        </div>
    </div>
</div>
<div class="row">
  <div class="col-lg-12 margin-tb">
      <div class="form-group" style="width:20%;">
        Filter by Status: {!! Form::select('filter_stat', $status_options, null,
                                           array('class' => 'form-control', 'id' => 'filter_stat')) !!}
      </div>
  </div>
</div>
<div class="row">
  <div class="col-lg-12 margin-tb">
      <div class="form-group" style="width:20%;">
        Filter by Provider: {!! Form::select('filter_prov', $providers, null,
                                             array('class' => 'form-control', 'id' => 'filter_prov')) !!}
      </div>
  </div>
</div>

<table class="table table-bordered" id="data_table">
 <tr>
    @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
    <th>Status</th>
    @endif
    <th>Year-Month</th>
    <th>Condition</th>
    <th>Report</th>
    @if ( auth()->user()->hasRole('Admin') )
    <th>Institution</th>
    @endif
    <th>Provider</th>
    <th>Last Updated</th>
    <th>Modified By</th>
  </tr>
  <tbody id="alertrows">
  @foreach ($records as $alert)
  <tr>
    @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
    <td>
      {!! Form::select($alert['stat_id'], $status_options, $alert['status'], array('class' => 'form-control')) !!}
    </td>
    @endif
    <td>{{ $alert['yearmon'] }}</td>
    <td><a href="{{ $alert['detail_url'] }}">{{ $alert['detail_txt'] }}</td>
    <td>{{ $alert['reportName'] }}</td>
    @if ( auth()->user()->hasRole('Admin') )
      <td>{{ $alert['inst_name'] }}</td>
    @endif
    <td>{{ $alert['prov_name'] }}</td>
    <td>{{ $alert['updated_at'] }}</td>
    <td>{{ $alert['mod_by'] }}</td>
  </tr>
  @endforeach
  </tbody>
</table>

@endsection
