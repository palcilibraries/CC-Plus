@extends('layouts.app')
@section('content')
<institution-form :institution="{{ json_encode($institution) }}"
                  :users="{{ json_encode($users) }}"
                  :unset_conso="{{ json_encode($unset_conso_providers) }}"
                  :unset_global="{{ json_encode($unset_global_providers) }}"
                  :all_connectors="{{ json_encode($all_connectors) }}"
                  :all_groups="{{ json_encode($all_groups) }}"
                  :all_roles="{{ json_encode($all_roles) }}"
                  :harvests="{{ json_encode($harvests) }}"
></institution-form>
@endsection
