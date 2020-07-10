@extends('layouts.app')

@section('content')
@if (sizeof($system_alerts) > 0)
    @foreach ($system_alerts as $alert)
      <div class="alert alert-{{ $alert->severity }}">System Alert :: <strong>{{ $alert->severity }}</strong> :: {{ $alert->text }}</div>
    @endforeach
@endif
<v-app>
  <v-content>
    <h1>{{ auth()->user()->name }}'s dashboard</h1>
    <h2 class="component-subhead">{{ $inst_count }} institution(s) and {{ $prov_count }} provider(s) connected</h2>
    <div class="dashboard-section">
      @if (sizeof($report_data) >= 1)
        <h2 class="actionable-subhead">My Saved Reports</h2>
      @else
        <p>No Custom Reports</p>
      @endif
      <a class="btn v-btn v-btn--contained v-size--small section-action" href="/reports/create">Create a Report</a>
      <home-saved-reports :reports="{{ json_encode($report_data) }}"></home-saved-reports>
    </div>

    @if (sizeof($data_alerts) > 0)
    <div class="dashboard-section">
      <alert-summary-table :alerts="{{ json_encode($data_alerts) }}"></alert-summary-table>
    </div>
    @endif

	<div class="dashboard-section">
	  <harvestlog-summary-table :harvests="{{ json_encode($harvests) }}"></harvestlog-summary-table>
    </div>
</v-app>
@endsection
