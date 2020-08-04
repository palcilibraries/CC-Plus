@extends('layouts.app')

@section('content')
<v-app>
  <alert-data-table :alerts="{{ json_encode($records) }}"
                    :sysalerts="{{ json_encode($sysalerts) }}"
                    :providers="{{ json_encode($providers) }}"
                    :statuses="{{ json_encode($statuses) }}"
                    :severities="{{ json_encode($severities) }}"
  ></alert-data-table>
</v-app>
@endsection
