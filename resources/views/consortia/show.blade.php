@extends('layouts.app')

@section('title', '| View Consortium')

@section('content')

<div class="container">

    <h1>{{ $consortium->title }}</h1>
    <hr>
    <p class="lead">{{ $consortium->name }} :: {{ $consortium->ccp_key }}</p>
    <hr>
    {!! Form::open(['method' => 'DELETE', 'route' => ['consortia.destroy', $consortium->id] ]) !!}
    <a href="{{ url()->previous() }}" class="btn btn-primary">Back</a>
    @can('Edit Consortium')
    <a href="{{ route('consortia.edit', $consortium->id) }}" class="btn btn-info" role="button">Edit</a>
    @endcan
    @can('Delete Consortium')
    {!! Form::submit('Delete', ['class' => 'btn btn-danger']) !!}
    @endcan
    {!! Form::close() !!}

</div>

@endsection
