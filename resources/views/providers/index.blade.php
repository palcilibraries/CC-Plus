@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      @if (auth()->user()->hasRole("Admin"))
      <h2>{{ session('ccp_con_key','') }} : Providers</h2>
      @else
      <h2>Providers</h2>
      @endif
    </div>
  </div>
</div>
<v-app>
  <provider-data-table :providers="{{ json_encode($data) }}"
                       :institutions="{{ json_encode($institutions) }}"
                       :master_reports="{{ json_encode($master_reports) }}"
  ></provider-data-table>
</v-app>
@endsection
