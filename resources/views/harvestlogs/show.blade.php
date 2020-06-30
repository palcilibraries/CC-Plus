@extends('layouts.app')

@section('content')
<v-app harvestlogform>
  <v-content>
    <div class="details">
      <h3 class="section-title">Harvest Details</h3>
      <table>
        <tr>
          <td>Institution: </td>
          <td width="20px"> &nbsp; </td>
          <td>{{ $harvest->sushiSetting->institution->name }}</td>
        </tr>
        <tr>
          <td>Provider: </td>
          <td> &nbsp; </td>
          <td>{{ $harvest->sushiSetting->provider->name }}</td>
        </tr>
        <tr>
          <td>Report: </td>
          <td> &nbsp; </td>
          <td>{{ $harvest->report->name }}</td>
        </tr>
        <tr>
          <td>Usage Month: </td>
          <td> &nbsp; </td>
          <td>{{ $harvest->yearmon }}</td>
        </tr>
        <tr>
          <td>Attempts: </td>
          <td> &nbsp; </td>
          <td>{{ $harvest->attempts }}</td>
        </tr>
        <tr>
          <td>Status: </td>
          <td> &nbsp; </td>
          <td>{{ $harvest->status }}</td>
        </tr>
      </table>
    </div>
    @if (sizeof($failed) > 0)
    <div class="related-list">
      <failed-by-harvest :failed_harvests="{{ json_encode($failed) }}"><failed-by-harvest>
    </div>
    @endif
  </v-content>
</v-app>
@endsection
