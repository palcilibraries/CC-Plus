@extends('layouts.app')
@section('content')
<div class="d-flex pl-2">
  <h3>{{ $conso_name }} Administration Dashboard</h3>
</div>
<consoadmin-dashboard :roles="{{ json_encode($roles) }}"
                      :institutions="{{ json_encode($institutions) }}"
                      :groups="{{ json_encode($groups) }}"
                      :providers="{{ json_encode($providers) }}"
                      :master_reports="{{ json_encode($master_reports) }}"
                      :unset_global="{{ json_encode($unset_global) }}"
></consoadmin-dashboard>
@endsection
