@extends('layouts.app')

@section('content')
<v-app providerform>
    <view-reports :counter_reports="{{ json_encode($counter_reports) }}"
                  :user_reports="{{ json_encode($user_reports) }}"
    ></view-reports>
</v-app>
@endsection
