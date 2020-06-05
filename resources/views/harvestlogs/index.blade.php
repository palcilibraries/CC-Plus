@extends('layouts.app')

@section('content')
<v-app>
  <harvestlog-data-table :harvests="{{ json_encode($harvests) }}"
                         :institutions="{{ json_encode($institutions) }}"
                         :providers="{{ json_encode($providers) }}"
                         :reports="{{ json_encode($reports) }}"
                         :bounds="{{ json_encode($bounds) }}"
                         :header="{{ json_encode($header) }}"
                         :filterable=1
  ></harvestlog-data-table>
</v-app>
@endsection
