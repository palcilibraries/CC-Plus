@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-lg-12 margin-tb">
    <div class="pull-left">
      <h2>Harvest Log</h2>
    </div>
  </div>
</div>
<v-app>
  <harvestlog-data-table :harvests="{{ json_encode($data) }}" :filterable=1></harvestlog-data-table>
</v-app>
@endsection
