@extends('layouts.app')

@section('title', '| Edit Consortium')

@section('content')
<div class="row">

    <div class="col-md-8 col-md-offset-2">

        <h1>Edit Consortium</h1>
        <hr>
            {{ Form::model($consortium, array('route' => array('consortia.update', $consortium->id), 'method' => 'PUT')) }}
            <div class="form-group">
            {{ Form::label('name', 'Name') }}
            {{ Form::text('name', null, array('class' => 'form-control')) }}<br>

            {{ Form::label('ccp_key', 'Key String') }}
            {{ Form::text('ccp_key', null, array('class' => 'form-control')) }}<br>

            {{ Form::label('email', 'Email Address') }}
            {{ Form::text('email', null, array('class' => 'form-control')) }}<br>

            {{ Form::label('is_active', 'Make Active?  ') }}
            {{ Form::checkbox('is_active', null, $consortium->is_active) }}<br>

            {{ Form::submit('Save', array('class' => 'btn btn-primary')) }}

            {{ Form::close() }}
    </div>
    </div>
</div>

@endsection
