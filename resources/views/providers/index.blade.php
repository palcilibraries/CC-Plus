@extends('layouts.app')
@section('content')
<div class="d-flex pl-2">
  @if (auth()->user()->hasRole("Admin"))
    <h3>{{ $conso_name }} : Providers</h3>
  @else
    <h3>Providers</h3>
  @endif
</div>
<provider-data-table :providers="{{ json_encode($providers) }}"
                     :institutions="{{ json_encode($institutions) }}"
                     :master_reports="{{ json_encode($master_reports) }}"
></provider-data-table>
@endsection
