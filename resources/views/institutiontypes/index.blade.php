@extends('layouts.app')

@section('content')
<div class="col-lg-12 margin-tb">
  <a href="{{ route('admin') }}"><< Back</a>
</div>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>Institution Type Management</h2>
        </div>
        <div class="pull-right">
            <a class="btn btn-success" href="{{ route('institutiontypes.create') }}">Create New Type</a>
        </div>
  		<v-row>
     	  <v-col cols="2"><h5>Export settings to:</h5></v-col>
    	  <v-col>
	 	    <a :href="'/institutiontypes/export/xls'">.xls</a> &nbsp; &nbsp;
		    <a :href="'/institutiontypes/export/xlsx'">.xlsx</a>
		  </v-col>
	    </v-row>
    </div>
</div>

@if ($message = Session::get('success'))
<flash class="alert-flash" message="{{ $message }}"></flash>
@endif

<table class="table table-bordered">
  <tr>
     <th>Institution Type</th>
     <th width="280px">Action</th>
  </tr>
  @foreach ($data as $key => $type)
  <tr>
      <td>{{ $type->name }}</td>
      <td>
        <a class="btn btn-primary" href="{{ route('institutiontypes.edit',$type->id) }}">Edit</a>
        {!! Form::open(['method' => 'DELETE','route' => ['institutiontypes.destroy', $type->id],
                                                         'style'=>'display:inline']) !!}
          {!! Form::submit('Delete', ['class' => 'btn btn-danger']) !!}
        {!! Form::close() !!}
      </td>
  </tr>
  @endforeach
</table>

{!! $data->render() !!}
@endsection
