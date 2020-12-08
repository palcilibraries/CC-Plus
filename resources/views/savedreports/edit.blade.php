@extends('layouts.app')

@section('content')
<v-app savedreportform>
  <v-main>
    <saved-report-form :report="{{ json_encode($report) }}"
                       :fields="{{ json_encode($fields) }}"
                       :filters="{{ json_encode($filters) }}"
                       :bounds="{{ json_encode($bounds) }}"
    ></saved-report-form>
  </v-main>
</v-app>
@endsection
