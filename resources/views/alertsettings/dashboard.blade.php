@extends('layouts.app')

@section('content')
<script type="text/javascript" src="{{ URL::asset('js/alertsettings.js') }}"></script>
<div class="col-lg-12 margin-tb">
  <a href="{{ route('admin') }}"><< Back</a>
</div>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>CC+ Alert Settings</h2>
        </div>
    </div>
</div>
<div class="row">
  <p align="center"><strong>
    Alerts are automatically set if/when a scheduled ingest fails or returns an error during processing.
    The settings below provide additional tests for create alerts based on boudaries or conditions related
    to specific data values and fields.  If a defined alert is enabled (checked), emails will be sent to
    affiliated users who have opted in to receive them. Reports including "alerted" datasets will be
    annotated in the display screen(s).</strong>
  </p>
</div>

@if ($message = Session::get('success'))
<flash class="alert-flash" message="{{ $message }}"></flash>
@endif
<form method="POST" action="{{route('alertsettings.store')}}">
 <input type="hidden" name="_token" value="{{ csrf_token() }}">
 <input type="hidden" name="_method" value="POST">
<!-- <form method="POST" action="/alertsettings/store" id="alert_settings"> -->
<table class="table table-bordered" id="data_table">
  <tr>
    <th>Send Alert</th>
    <th>Whenever</th>
    @if ( auth()->user()->hasRole('Admin') )
    <th>For</th> <!-- Institution Column -->
    @endif
    <th>Varies by +/-</th>
    <th>Versus the past</th>
  </tr>
  <tbody id="settingrows">
  @foreach ($data as $key => $setting)
  <tr>
    <td width="10%">
      {!! Form::checkbox('cb_'.$setting->id,null,$setting->is_active) !!}
      <input type="hidden" name="met_{{ $setting->id }}" value="{{ $setting->reportField->id }}">
    </td>
    <?php $condition = $setting->reportField->report->name."(v".$setting->reportField->report->revision.")".
                       " :: ".$setting->reportField->legend;?>
    <td width="35%">{{ $condition }}</td>
    @if ( auth()->user()->hasRole('Admin') )
       <?php $_inst_name = ($setting->institution->id < 2) ? 'Entire Consortium' : $setting->institution->name; ?>
    <td width="25%">{{ $_inst_name }}
    @else
    <td style="display:none">
    @endif
      <input type="hidden" name="inst_{{ $setting->id }}" value="{{ $setting->inst_id }}">
    </td>
    <td width="10%">
      {!! Form::number('var_'.$setting->id, $setting->variance,
                       array('min' => 0, 'max' => 1000, 'class' => 'form-control')) !!} %
    </td>
    <td width="10%">
      {!! Form::number('time_'.$setting->id, $setting->timespan,
                       array('min' => 0, 'max' => 48, 'class' => 'form-control')) !!} months
    </td>
    <td width="10%">
      <td align="center"><button class="btn btn-danger" type="button" id="destroy_{{$setting->id}}">Delete</button>
    </td>
  </tr>
  @endforeach
  </tbody>
  <tbody id="buttons_row">
    <tr>
      <td colspan="2" align="center"><button class="btn btn-primary" id="newsetting" type="button">Create New Alert</button></div>
      <td align="center"><button class="btn btn-primary" type="button" id="reset_form">Reset</button></div>
      <td align="center"><button class="btn btn-primary" type="submit">Save</button></div>
    </tr>
  </tbody>
</table>
{!! Form::close() !!}
<form method="get" id="add_setting">
<table id="addrow_table" style="display:none">
  <tbody id="newrow">
  <tr>
    <th width="10%">Report</th>
    <th width="40%">Condition</th>
    @if ( auth()->user()->hasRole('Admin') )
    <th width="20%">Applies To</th> <!-- Institution Column -->
    @endif
    <th width="10%">%-Age Variation</th>
    <th width="10%">Over #-months</th>
  </tr>
  <tr>
    <td>
      {!! Form::select('A_report', $reports, 'Choose a Report',  array('class' => 'form-control', 'id' => 'A_report')) !!}
    </td>
    <td>
      {!! Form::select('A_field', array(), 'Choose a Measure',  array('class' => 'form-control', 'id' => 'A_field')) !!}
    </td>
    @if ( auth()->user()->hasRole('Admin') )
    <td>{!! Form::select('A_inst', $institutions, [], array('class' => 'form-control', 'id' => 'A_inst')) !!}</td>
    @endif
    <td>{!! Form::number('A_variance',null,array('min' => 0, 'max' => 1000, 'class' => 'form-control', 'id' => 'A_variance')) !!}</td>
    <td>{!! Form::number('A_timespan',null,array('min' => 0, 'max' => 48, 'class' => 'form-control', 'id' => 'A_timespan')) !!}</td>
  </tr>
  <tr>
    <td colspan="3" align="center"><button class="btn btn-primary" type="button" id="create_cancel">Cancel</button></div>
    <td align="center"><button class="btn btn-primary" type="button" id="create_save">Save</button></div>
  </tr>
  </tbody>
</table>
{!! Form::close() !!}

@endsection
