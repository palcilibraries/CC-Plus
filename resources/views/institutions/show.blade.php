@extends('layouts.app')
@section('content')
<v-app institutionform>
    <institution-form :institution="{{ json_encode($institution) }}"
                      :users="{{ json_encode($users) }}"
                      :unset="{{ json_encode($unset_providers) }}"
                      :all_connectors="{{ json_encode($all_connectors) }}"
                      :all_groups="{{ json_encode($all_groups) }}"
                      :all_roles="{{ json_encode($all_roles) }}"
                      :harvests="{{ json_encode($harvests) }}"
    ></institution-form>
</v-app>
@endsection
