@extends('layouts.app')

@section('content')
<v-app>
  <alert-data-table :alerts="{{ json_encode($alerts) }}"
                    :sysalerts="{{ json_encode($sysalerts) }}"
                    :providers="{{ json_encode($providers) }}"
                    :statuses="{{ json_encode($statuses) }}"
                    :severities="{{ json_encode($severities) }}"
                    :institutions="{{ json_encode($institutions) }}"
                    :reports="{{ json_encode($reports) }}"
                    :bounds="{{ json_encode($bounds) }}"
                    :filters="{{ json_encode($filters) }}"
  ></alert-data-table>
</v-app>
@endsection
