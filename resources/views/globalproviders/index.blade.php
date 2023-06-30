@extends('layouts.app')
@section('content')
<div class="d-flex pl-2"><h1>Global Providers</h1></div>
<global-provider-data-table :providers="{{ json_encode($providers) }}"
                            :master_reports="{{ json_encode($masterReports) }}"
                            :all_connectors="{{ json_encode($all_connectors) }}"
                            :filters="{{ json_encode($filters) }}"
></global-provider-data-table>
@endsection
