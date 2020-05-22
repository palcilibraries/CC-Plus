@extends('layouts.app')

@section('content')
<v-app>
  <harvestlog-data-table :harvests="{{ json_encode($data) }}"
                         :header="{{ json_encode($header) }}"
                         :filterable=1
  ></harvestlog-data-table>
</v-app>
@endsection
