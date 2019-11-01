@extends('layouts.app')

@section('content')
<div class="col-lg-12 margin-tb">
  <a href="{{ route('admin') }}"><< Back</a>
</div>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>User Management</h2>
        </div>
        <div class="pull-right">
            <a class="btn btn-success" href="{{ route('users.create') }}"> Create New User</a>
        </div>
    </div>
</div>

@if ($message = Session::get('success'))
<flash class="alert-flash" message="{{ $message }}"></flash>
@endif

<table class="table table-bordered">
 <tr>
   <th>Active</th>
   <th>Name</th>
   <th>Email</th>
   <th>Institution</th>
   <th>Roles</th>
   <th width="280px">Action</th>
 </tr>

 @foreach ($data as $key => $user)
  <tr>
    <td>{{ $user->is_active ? "Yes" : "No" }}</td>
    <td>{{ $user->name }}</td>
    <td>{{ $user->email }}</td>
    <td>{{ $user->institution->name }}</td>
    <td>
       @foreach($roles as $r)
          @if($user->hasRole($r))
             <label class="badge badge-success">{{ $r }} </label>
          @endif
       @endforeach
    </td>
    @if($user->canManage())
    <td>
       <a class="btn btn-info" href="{{ route('users.show',$user->id) }}">Show</a>
       <a class="btn btn-primary" href="{{ route('users.edit',$user->id) }}">Edit</a>
        {!! Form::open(['method' => 'DELETE','route' => ['users.destroy', $user->id],'style'=>'display:inline']) !!}
            {!! Form::submit('Delete', ['class' => 'btn btn-danger']) !!}
        {!! Form::close() !!}
    </td>
    @else
    <td>&nbsp;</td>
    @endif
  </tr>
 @endforeach
</table>

{!! $data->render() !!}

@endsection
