@extends('layouts.app')

@section('content')
@if (sizeof($alerts) > 0)
    @foreach ($alerts as $alert)
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

    <div class="dashboard-section">
      <alert-summary-table :alerts="{{ json_encode($alerts) }}"></alert-summary-table>
    </div>

    <div class="row">
      <div class="col-lg-12 margin-tb">
          <div class="pull-left">
              <h2>Recent Failed Harvests</h2>
          </div>
      </div>
    </div>
    <table width="80%" cellspacing="2">
      <tr>
         <th width="15%">Harvest Date</th>
         <th width="25%">Provider</th>
         <th width="10%">Report</th>
         <th width="10%">Institutions affected</th>
      </tr>
      @if (sizeof($failed_data) >= 1)
        @foreach ($failed_data as $record)
        <tr>
            <td>{{ $record->harvest_date }}</td>
            <td>{{ $record->provider }}</td>
            <td>{{ $record->report }}</td>
            <td>{{ $record->failed_insts }} / {{ $total_insts }}</td>
        </tr>
        @endforeach
        <tr>
          <td colspan="4" align="center">
            <a href="/failedharvests">View all failed harvests</a>
          </td>
        </tr>
      @else
        <tr>
            <td colspan="4"><strong>No failed harvests found</strong>
        </tr>
      @endif
    </table>
</v-app>
@endsection
