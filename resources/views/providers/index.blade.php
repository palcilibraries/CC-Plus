@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      @if (auth()->user()->hasRole("Admin"))
      <h3>{{ session('ccp_con_key','') }} : Providers</h3>
      @else
      <h3>Providers</h3>
      @endif
    </div>
  </div>
</div>
<v-app>
  <provider-data-table :providers="{{ json_encode($providers) }}"
                       :institutions="{{ json_encode($institutions) }}"
                       :unset_global="{{ json_encode($unset_global) }}"
  ></provider-data-table>
</v-app>
@endsection
