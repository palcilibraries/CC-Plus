@extends('layouts.app')

@section('title', '| Create New Consortium')

@section('content')
    <div class="row">
        <div class="col-md-8 col-md-offset-2">

        <h1>Create New Consortium</h1>
        <hr>

    {{-- Using the Laravel HTML Form Collective to create our form --}}
        {{ Form::open(array('route' => 'consortia.store')) }}

        <div class="form-group">
            {{ Form::label('name', 'Name') }}
            {{ Form::text('name', null, array('class' => 'form-control')) }}
            <br>

            {{ Form::label('ccp_key', 'Key String') }}
            {{ Form::text'ccp_key', null, array('class' => 'form-control')) }}
            <br>

            {{ Form::label('email', 'Email Address') }}
            {{ Form::text('email', null, array('class' => 'form-control')) }}
            <br>

            {{ Form::label('is_active', 'Make Active?') }}
            {{ Form::checkbox('is_active', null, array('class' => 'form-control')) }}
            <br>

            {{ Form::submit('Create Consortium', array('class' => 'btn btn-success btn-lg btn-block')) }}
            {{ Form::close() }}
        </div>
        </div>
    </div>

@endsection

