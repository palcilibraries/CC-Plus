@extends('layouts.app')

@section('content')
<script type="text/javascript" src="{{ URL::asset('js/institutions.js') }}"></script>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-right">
          @if ( auth()->user()->hasRole('Admin') )
            <a class="btn btn-primary" href="{{ route('institutions.index') }}"> Back</a>
          @else
            <a class="btn btn-primary" href="{{ route('admin') }}"> Back</a>
          @endif
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

<table>
  <tr>
    <th width="5%">&nbsp;</th>
    <th width="40%"><h3>Editting settings for : {{ $institution->name }} (id: {{ $institution->id }})</h3></th>
    <th width="10%">&nbsp;</th>
    <td width="40%"><h3>Update Sushi settings by Provider</h3></th>
    <th width="5%">&nbsp;</th>
  </tr>
  <tr>
    <td colspan="3" align="center">&nbsp;</td>
    <td colspan="2" align="center"><div style="display:none; color:red;" id="notice"></div></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td valign="top">
      {!! Form::model($institution, ['method' => 'PATCH','route' => ['institutions.update', $institution->id]]) !!}
      <div class="row">
          <div class="col-xs-12 col-sm-12 col-md-12">
              <div class="form-group">
                  <strong>Name:</strong>
                  {!! Form::text('name', null, array('placeholder' => 'Name','class' => 'form-control')) !!}
              </div>
          </div>
          <div class="col-xs-12 col-sm-12 col-md-12">
              <div class="form-group">
                  <strong>Status:</strong>
                  {!! Form::select('is_active', ['1'=>'Active', '0'=>'Inactive'], array('class' => 'form-control')) !!}
              </div>
          </div>
          <div class="col-xs-12 col-sm-12 col-md-12">
              <div class="form-group">
                  <strong>FTE:</strong>
                  {!! Form::number('fte', $institution->fte, array('min' => 0, 'class' => 'form-control')) !!}
              </div>
          </div>
          <div class="col-xs-12 col-sm-12 col-md-12">
              <div class="form-group">
                  <strong>Institution Type:</strong>
                  {!! Form::select('type_id', $types, $institution->type, array('class' => 'form-control')) !!}
              </div>
          </div>
          @if ( auth()->user()->hasRole('Admin') )
          <div class="col-xs-12 col-sm-12 col-md-12">
              <div class="form-group">
                  <strong>Group Assignments:</strong>
                  {!! Form::select('institutiongroups[]', $all_groups, $inst_groups, array('class' => 'form-control','multiple')) !!}
              </div>
          </div>
          @endif
          <div class="col-xs-12 col-sm-12 col-md-12">
              <div class="form-group">
                  <strong>Notes</strong>
                  {!! Form::textarea('notes', null, array('class' => 'form-control', 'rows' => 3, 'cols' => 60)) !!}
              </div>
          </div>
          <div class="col-xs-12 col-sm-12 col-md-12 text-center">
              <button type="submit" id="SaveInst" class="btn btn-primary">Update Institution</button>
          </div>
      </div>
      {!! Form::close() !!}
    </td>
    <td>&nbsp;</td>
    <td valign="top">
      <form method="post" id="sushi_settings">
      @csrf
      <input type="hidden" value="{{ $institution->id }}" name="inst_id" id="INST">
      <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Provider:</strong>
                {!! Form::select('prov_id', $providers, 0, array('class' => 'form-control', 'id' => 'Prov')) !!}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Customer ID:</strong>
                {!! Form::text('customer_id', null, array('placeholder' => 'Customer ID','class' => 'form-control',
                'id' => 'Sushi_CustID')) !!}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>Requestor ID:</strong>
                {!! Form::text('requestor_id', null, array('placeholder' => 'Requestor ID','class' => 'form-control',
                                                          'id' => 'Sushi_ReqID')) !!}
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-12">
            <div class="form-group">
                <strong>API Key:</strong>
                {!! Form::text('API_key', null, array('placeholder' => 'API Key','class' => 'form-control',
                                                          'id' => 'Sushi_APIkey')) !!}
            </div>
        </div>

        <div class="col-xs-12 col-sm-12 col-md-12 text-center">
          <button type="button" id="SaveSushi" class="btn btn-primary">Update Sushi Settings</button>
        </div>
      </div>
      {!! Form::close() !!}
    </td>
    <td>&nbsp;</td>
  </tr>
</table>
{!! Form::close() !!}

@endsection
