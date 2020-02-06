@extends('layouts.app')

@section('content')
<div class="col-lg-12 margin-tb">
  <a href="{{ route('admin') }}"><< Back</a>
</div>
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2>Provider Management</h2>
        </div>
        <div class="pull-right">
            <a class="btn btn-success" href="{{ route('providers.create') }}"> Create New Provider</a>
        </div>
    </div>
</div>

@if ($message = Session::get('success'))
<flash class="alert-flash" message="{{ $message }}"></flash>
@endif

<table class="table table-bordered">
 <tr>
   <th>Provider</th>
   <th>Status</th>
   <th>Serves</th>
   <th>Harvest Day</th>
   <th width="280px">Action</th>
 </tr>

 @foreach ($data as $key => $provider)
  <?php $inst_name = ($provider->institution->id == 1) ? "Entire Consortium" : $provider->institution->name; ?>
  <tr>
    <td>{{ $provider->name }}</td>
    <td>{{ $provider->is_active ? 'Active' : 'Inactive' }}</td>
    <td>{{ $inst_name }}</td>
    <td>{{ $provider->day_of_month }}</td>
    <td>
      @if ( auth()->user()->hasRole('Admin') ||
           (auth()->user()->hasRole('Manager') && $provider->inst_id == auth()->user()->inst_id) )
        <a class="btn btn-info" href="{{ route('providers.show',$provider->id) }}">Show</a>
        <a class="btn btn-primary" href="{{ route('providers.edit',$provider->id) }}">Edit</a>
        {!! Form::open(['method' => 'DELETE','route' => ['providers.destroy', $provider->id],
                                   'style'=>'display:inline']) !!}
          {!! Form::submit('Delete', ['class' => 'btn btn-danger']) !!}
        {!! Form::close() !!}
      @endif
    </td>
  </tr>
 @endforeach
</table>

{!! $data->render() !!}
@endsection
