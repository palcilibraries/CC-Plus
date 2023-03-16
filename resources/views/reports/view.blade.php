@extends('layouts.app')

@section('content')
<v-app providerform>
    <view-reports :counter_reports="{{ json_encode($counter_reports) }}"></view-reports>
</v-app>
@endsection
