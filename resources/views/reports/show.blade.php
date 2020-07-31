@extends('layouts.app')

@section('content')
<v-app savedreportform>
  <v-main>
    <show-counter-report :report="{{ json_encode($report) }}"
                         :fields="{{ json_encode($fields) }}"
                         :filters="{{ json_encode($filters) }}"
    ></show-counter-report>
</v-main>
</v-app>
@endsection
