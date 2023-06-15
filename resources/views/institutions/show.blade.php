@extends('layouts.app')
@section('content')
<institution-form :institution="{{ json_encode($institution) }}"
                  :all_providers="{{ json_encode($all_providers) }}"
                  :all_groups="{{ json_encode($all_groups) }}"
                  :all_roles="{{ json_encode($all_roles) }}"
                  :harvests="{{ json_encode($harvests) }}"
                  :unset_global="{{ json_encode($unset_global) }}"
                  :master_reports="{{ json_encode($master_reports) }}"
></institution-form>
@endsection
