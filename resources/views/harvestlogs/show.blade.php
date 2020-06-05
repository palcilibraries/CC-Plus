@extends('layouts.app')

@section('content')
<v-app harvestlogform>
  <v-content>
    <harvestlog-form :harvest="{{ json_encode($harvest) }}"></harvestlog-form>
    @if (sizeof($failed) > 0)
    <div class="related-list">
      <failed-harvests :failed_harvests="{{ json_encode($failed) }}"><failed-harvests>
    </div>
    @endif
  </v-content>
</v-app>
@endsection
