@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-right">
          @if ( auth()->user()->hasRole('Admin') )
            <a class="btn btn-primary" href="{{ route('providers.index') }}"> Back</a>
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
    <th width="50%"><h3>Settings for : {{ $provider->name }} (id: {{ $provider->id }})</h3></th>
    <td width="50%"><h3>Update Sushi settings by Institution</h3></th>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td align="center"><div style="display:none; color:red;" id="notice"></div></td>
  </tr>
  <tr>
    <td valign="top">
        <provider-form :provider="{{ json_encode($_prov) }}"
                       :institutions="{{ json_encode($institutions) }}"
                       :master_reports="{{ json_encode($master_reports) }}"
                       :provider_reports="{{ json_encode($provider_reports) }}"
        ></provider-form>
    </td>
    <td valign="top">
        <sushi-by-inst :prov_id="{{ $provider->id }}" :institutions="{{ json_encode($sushi_insts) }}"></sushi-by-inst>
    </td>
  </tr>
</table>

@endsection
