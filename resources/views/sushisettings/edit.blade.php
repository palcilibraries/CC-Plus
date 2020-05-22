@extends('layouts.app')

@section('content')
<v-app sushisettingform>

    <div>
    	<div class="page-header">
    	    <h1>{{ $setting->institution->name }} / {{ $setting->provider->name }} </h1>
    	</div>
    </div>

    <sushi-setting-form :setting="{{ json_encode($setting) }}"></sushi-setting-form>

    <div class="related-list">
  	    <h2 class="section-title">Activity</h2>
        <harvestlog-data-table :harvests="{{ json_encode($harvests) }}"></harvestlog-data-table>
    </div>

</v-app>


@endsection
