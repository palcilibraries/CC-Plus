@extends('layouts.app')

@section('content')
<v-app>
  <v-content>
    <table>
      <tr><td><h3>{{ auth()->user()->name }}'s dashboard</h3></td></tr>
      <tr><td><h4>{{ $inst_count }} institution(s) and {{ $prov_count }} provider(s) connected</h4></td></tr>
      <tr>
        <td><h5>My Saved Reports<h5></td>
        <td><a class="btn btn-success" href="/reports/create">Create a Report</a></td>
      </tr>
    </table>
    <home-saved-reports :reports="{{ json_encode($report_data) }}"></home-saved-reports>
</v-app>
@endsection
