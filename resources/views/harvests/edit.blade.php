@extends('layouts.app')

@section('content')
<v-app harvestlogform>
  <v-main>
    <harvestlog-form :harvest="{{ json_encode($harvest) }}"
                     :last_attempt="{{ json_encode($attempts[0]) }}"
    ></harvestlog-form>
    @if (sizeof($attempts) > 0)
    <div class="related-list">
      <harvest-attempts :attempts="{{ json_encode($attempts) }}"><harvest-attempts>
    </div>
    @endif
  </v-main>
</v-app>
@endsection
