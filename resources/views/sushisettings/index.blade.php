@extends('layouts.app')

@section('content')
<v-app>
  <sushisettings-data-table :all_connectors="{{ json_encode($all_connectors) }}"
                         :institutions="{{ json_encode($institutions) }}"
                         :providers="{{ json_encode($providers) }}"
                         :filters="{{ json_encode($filters) }}"
  ></sushisettings-data-table>
</v-app>
@endsection
