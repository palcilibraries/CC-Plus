@extends('layouts.app')
@section('content')
<globaladmin-dashboard :consortia="{{ json_encode($consortia) }}"
                       :settings="{{ json_encode($settings) }}"
                       :providers="{{ json_encode($providers) }}"
                       :provider_filters="{{ json_encode($filters) }}"
                       :master_reports="{{ json_encode($masterReports) }}"
                       :all_connectors="{{ json_encode($all_connectors) }}"
></globaladmin-dashboard>
@endsection
