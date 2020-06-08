@extends('layouts.app')

@section('content')
@if (sizeof($alerts) > 0)
  <table>
    @foreach ($alerts as $alert)
    <tr>
      <td align="center">
        <h5>System Alert :: <strong>{{ $alert->severity }}</strong> :: {{ $alert->text }}</h5></span>
      </td>
    </tr>
    @endforeach
  </table>
@endif
<v-app>
  <v-content>
    <table>
      <tr><td><h3>{{ auth()->user()->name }}'s dashboard</h3></td></tr>
      <tr><td><h4>{{ $inst_count }} institution(s) and {{ $prov_count }} provider(s) connected</h4></td></tr>
      <tr>
        @if (sizeof($report_data) >= 1)
          <td><h5>My Saved Reports<h5></td>
        @else
          <td><h5>No Custom Reports<h5></td>
        @endif
        <td><a class="btn btn-success" href="/reports/create">Create a Report</a></td>
      </tr>
    </table>
    <home-saved-reports :reports="{{ json_encode($report_data) }}"></home-saved-reports>

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
