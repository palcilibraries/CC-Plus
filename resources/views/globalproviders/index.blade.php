@extends('layouts.app')
@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      <h3>Global Providers</h3>
    </div>
  </div>
</div>
<v-app>
  <global-provider-data-table :providers="{{ json_encode($providers) }}"
                              :master_reports="{{ json_encode($master_reports) }}"
                              :all_connectors="{{ json_encode($all_connectors) }}"
                              :default_retries="{{ $default_retries }}"
  ></global-provider-data-table>
</v-app>
@endsection
