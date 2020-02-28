@extends('layouts.app')

@section('content')
@if (count($errors) > 0)
  <div class="alert alert-danger">
    <strong>Whoops!</strong> There were some problems with your input.<br><br>
    <ul>
       @foreach ($errors->all() as $error)
         <li>{{ $error }}</li>
       @endforeach
    </ul>
  </div>
@endif

<table width="100%">
  <tr>
    <td align="center"><div style="display:none; color:red;" id="notice"></div></td>
  </tr>
</table>
<v-app institutionform>
  <table width="100%">
    <tr>
      <td width="49%" valign="top">
        <v-expansion-panels multiple focusable :value="[0]">
          <v-expansion-panel>
            <v-expansion-panel-header>
              <h3>Settings for : {{ $provider->name }} (id: {{ $provider->id }})</h3>
            </v-expansion-panel-header>
            <v-expansion-panel-content>
              @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
              <provider-form :provider="{{ json_encode($_prov) }}"
                             :institutions="{{ json_encode($institutions) }}"
                             :master_reports="{{ json_encode($master_reports) }}"
                             :provider_reports="{{ json_encode($provider_reports) }}"
                             :manager="{{ auth()->user()->hasAnyRole(['Admin','Manager']) }}"
              ></provider-form>
              @else
              <provider-view :provider="{{ json_encode($_prov) }}"
                             :institution="{{ json_encode($provider->institution->name) }}"
                             :master_reports="{{ json_encode($master_reports) }}"
                             :provider_reports="{{ json_encode($provider_reports) }}"
              ></provider-view>
              @endif
          </v-expansion-panel-content>
        </v-expansion-panel>
    </td>
    <td width="2%">&nbsp;</td>
    <td width="49%" valign="top">
      <v-expansion-panels multiple focusable :value="[0]">
        <v-expansion-panel>
          <v-expansion-panel-header>
            Sushi Settings for : {{ $provider->name }}
          </v-expansion-panel-header>
          <v-expansion-panel-content>
            <sushi-by-inst :prov_id="{{ $provider->id }}"
                           :institutions="{{ json_encode($sushi_insts) }}"
                           :manager="{{ auth()->user()->hasAnyRole(['Admin','Manager']) }}"
            ></sushi-by-inst>
          </v-expansion-panel-content>
        </v-expansion-panel>
      </v-expansion-panels>
    </td>
  </tr>
</table>

@endsection
