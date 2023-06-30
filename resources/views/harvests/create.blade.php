@extends('layouts.app')
@section('content')
<h1>Manual Harvesting</h1>
<p>You can add harvests to the <a href="/harvestlogs">harvesting queue</a> manually once settings are defined
    to connect <a href="/providers">provider</a> services with one more <a href="/institutions">institutions</a>.
</p>
<p>The harvesting queue is automatically scanned on a preset interval, set by the CC-Plus administrator,
   and will process all harvest requests on a first-in first-out basis.
   <h5>Note:</h5>
   <ul>
    <li>Requesting a manual harvest for a previously harvested provider, institition, and month,
        will re-initialize the harvest as a <strong>new</strong> entry with zero attempts.</li>
    <li>On successful retrieval, manually harvested data will replace (overwrite) all previously
        harvested report data for a given institution->provider->month.</li>
   </ul>
</p>
<manual-harvest :institutions="{{ json_encode($institutions) }}"
                :inst_groups="{{ json_encode($inst_groups) }}"
                :providers="{{ json_encode($providers) }}"
                :all_reports="{{ json_encode($all_reports) }}"
                :presets="{{ json_encode($presets) }}"
></manual-harvest>
@endsection
