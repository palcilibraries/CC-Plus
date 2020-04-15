@extends('layouts.app')

@section('content')
<v-app reportwiz>
  <create-report :institutions="{{ json_encode($institutions) }}"
                 :inst_groups="{{ json_encode($inst_groups) }}"
                 :providers="{{ json_encode($providers) }}"
                 :reports="{{ json_encode($reports) }}"
                 :fields="{{ json_encode($fields) }}"
  ></create-report>
</v-app>
@endsection
