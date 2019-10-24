@extends('layouts.app')

@section('content')
<div class="col-lg-12 margin-tb">
  <a href="{{ route('admin') }}"><< Back</a>
</div>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>Institution Group Management</h2>
        </div>
        <div class="pull-right">
            <a class="btn btn-success" href="{{ route('institutiongroups.create') }}">Create New Group</a>
        </div>
    </div>
</div>

@if ($message = Session::get('success'))
<div class="alert alert-success">
  <p>{{ $message }}</p>
</div>
@endif

<table class="table table-bordered">
  <tr>
     <th>Institution Group</th>
     <th width="280px">Action</th>
  </tr>
  @foreach ($data as $key => $group)
  <tr>
      <td>{{ $group->name }}</td>
      <td>
        <a class="btn btn-primary" href="{{ route('institutiongroups.edit',$group->id) }}">Edit</a>
        {!! Form::open(['method' => 'DELETE','route' => ['institutiongroups.destroy', $group->id],
                                                         'style'=>'display:inline']) !!}
          {!! Form::submit('Delete', ['class' => 'btn btn-danger']) !!}
        {!! Form::close() !!}
      </td>
  </tr>
  @endforeach
</table>

{!! $data->render() !!}
@endsection
