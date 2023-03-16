@extends('layouts.app')

@section('content')
<v-app>
  <v-main>
    <div class="dashboard-section">
      @if (sizeof($report_data) >= 1)
        <h3 class="actionable-subhead">My Saved Reports</h3>
      @else
        <p>You Have No Saved Reports (yet)</p>
      @endif
      <a class="btn v-btn v-btn--contained v-size--small section-action" href="/reports/create">Create a Report</a>
      <home-saved-reports :reports="{{ json_encode($report_data) }}"></home-saved-reports>
    </div>
  </v-main>
</v-app>
@endsection
