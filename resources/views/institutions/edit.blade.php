@extends('layouts.app')

@section('content')
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

<table width="100%">
  <tr>
    <th width="50%"><h3>Settings for : {{ $institution->name }} (id: {{ $institution->id }})</h3></th>
    <td width="50%"><h3>Update Sushi settings by Provider</h3></th>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td align="center"><div style="display:none; color:red;" id="notice"></div></td>
  </tr>
  <tr>
    <td valign="top">
        <institution-form :institution="{{ json_encode($_inst) }}"
                          :providers="{{ json_encode($providers) }}"
                          :types="{{ json_encode($types) }}"
                          :inst_groups="{{ json_encode($inst_groups) }}"
                          :all_groups="{{ json_encode($all_groups) }}"
        ></institution-form>
    </td>
    <td valign="top">
        <sushi-by-prov :inst_id="{{ $institution->id }}" :providers="{{ json_encode($providers) }}"></sushi-by-inst>
    </td>
  </tr>
</table>

@endsection
