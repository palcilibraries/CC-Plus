@extends('layouts.app')

@section('content')
<script type="text/javascript" src="{{ URL::asset('js/alerts.js') }}"></script>
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
        <?php $__options = preg_replace("/,/",":",preg_replace("/(\[|\"|\])/","",
                                        json_encode(array_keys($status_options)))); ?>
        <input type="hidden" name="enum_stat" id="enum_stat" value="{{$__options}}">
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
    @if ( auth()->user()->hasAnyRole('Admin','Manager') )
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
  @foreach ($data as $key => $alert)
  <tr>
    @if ( auth()->user()->hasAnyRole('Admin','Manager') )
      <?php $st_id = "stat_".$alert->id; ?>
      <td>{!! Form::select($st_id, ['Active'=>'Active','Silent'=>'Silent','Delete'=>'Delete'],
                       $alert->status, array('class' => 'form-control')) !!}</td>
    @endif
    <td>{{ $alert->yearmon }}</td>
    <td>{{ $alert->detail() }}</td>
    <td>{{ $alert->reportName() }}</td>
    @if ( auth()->user()->hasRole('Admin') )
       <?php $_inst_name = ($alert->institution()->id < 2) ? 'Consortia-wide' : $alert->institution()->name; ?>
      <td>{{ $_inst_name }}</td>
    @endif
    <td>{{ $alert->provider->name }}</td>
    <td>{{ $alert->updated_at }}</td>
    <?php $_modname = ($alert->modified_by == 0) ? 'CC-Plus System' : $alert->user->name; ?>
    <td>{{ $_modname }}</td>
  </tr>
  @endforeach
  </tbody>
</table>

@endsection
