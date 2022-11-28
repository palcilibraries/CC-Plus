@extends('layouts.app')

@section('content')
<v-app providerform>
    <provider-form :provider="{{ json_encode($provider) }}"
                   :institutions="{{ json_encode($institutions) }}"
                   :unset="{{ json_encode($unset_institutions) }}"
                   :master_reports="{{ json_encode($master_reports) }}"
                   :all_fields="{{ json_encode($all_fields) }}"
                   :harvests="{{ json_encode($harvests) }}"
    ></provider-form>
</v-app>
@endsection
