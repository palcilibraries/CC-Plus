@extends('layouts.app')
@section('page_title')
    {{ "Consortium Admin -- " . $conso_name . " -- CC-Plus" }}
@endsection
@section('content')
<div class="d-flex pl-2">
  <h1>{{ $conso_name }} Administration Dashboard</h1>
</div>
<consoadmin-dashboard :roles="{{ json_encode($roles) }}"
                      :institutions="{{ json_encode($institutions) }}"
                      :groups="{{ json_encode($groups) }}"
                      :providers="{{ json_encode($providers) }}"
                      :master_reports="{{ json_encode($master_reports) }}"
                      :unset_global="{{ json_encode($unset_global) }}"
></consoadmin-dashboard>
@endsection
