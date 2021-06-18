@extends('layouts.app')

@section('content')
<v-app>
  <harvestlog-data-table :harvests="{{ json_encode($harvests) }}"
                         :institutions="{{ json_encode($institutions) }}"
                         :groups="{{ json_encode($groups) }}"
                         :providers="{{ json_encode($providers) }}"
                         :reports="{{ json_encode($reports) }}"
                         :bounds="{{ json_encode($bounds) }}"
                         :filters="{{ json_encode($filters) }}"
  ></harvestlog-data-table>
</v-app>
@endsection
