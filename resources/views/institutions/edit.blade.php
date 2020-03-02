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
              <h4>Settings for : {{ $institution->name }}</h4>
            </v-expansion-panel-header>
            <v-expansion-panel-content>
              <institution-form :institution="{{ json_encode($_inst) }}"
                                :providers="{{ json_encode($providers) }}"
                                :types="{{ json_encode($types) }}"
                                :inst_groups="{{ json_encode($inst_groups) }}"
                                :all_groups="{{ json_encode($all_groups) }}"
                                :manager="{{ auth()->user()->hasAnyRole(['Admin','Manager']) }}"
              ></institution-form>
            </v-expansion-panel-content>
          </v-expansion-panel>
          @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
          <v-expansion-panel>
            <v-expansion-panel-header>
              <h4>User Accounts for : {{ $institution->name }}</h4>
            </v-expansion-panel-header>
            <v-expansion-panel-content>
              <p>hello world.</p>
            </v-expansion-panel-content>
          </v-expansion-panel>
          @endif
        </v-expansion-panels>
      </td>
      <td width="2%">&nbsp;</td>
      <td width="49%" valign="top">
        <v-expansion-panels multiple focusable :value="[0]">
          <v-expansion-panel>
            <v-expansion-panel-header>
              <h4>Sushi Settings for : {{ $institution->name }}</h4>
            </v-expansion-panel-header>
            <v-expansion-panel-content>
              <sushi-by-prov :inst_id="{{ $institution->id }}"
                             :providers="{{ json_encode($providers) }}"
                             :admin="{{ auth()->user()->hasRole("Admin") }}"
              ></sushi-by-inst>
            </v-expansion-panel-content>
          </v-expansion-panel>
        </v-expansion-panels>
      </td>
    </tr>
  </table>
</v-app>
@endsection
