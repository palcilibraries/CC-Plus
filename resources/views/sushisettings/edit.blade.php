@extends('layouts.app')

@section('content')
<v-app sushisettingform>

    <div>
    	<div class="page-header">
    	    <h2><a href="/institutions/{{ $setting->institution->id }}">{{ $setting->institution->name }}</a> /
                <a href="/providers/{{ $setting->provider->id }}">{{ $setting->provider->name }}</a></h2>
    	</div>
    </div>

    <sushi-setting-form :setting="{{ json_encode($setting) }}"></sushi-setting-form>
    @if (sizeof($harvests) > 0)
    <div class="related-list">
      <h3>Recent Harvest Activity</h3>
      <harvestlog-summary-table :harvests="{{ json_encode($harvests) }}"
                                :inst_id="{{ $setting->inst_id }}"
                                :prov_id="{{ $setting->prov_id }}"
      ></harvestlog-summary-table>
    </div>
    @endif
</v-app>
@endsection
