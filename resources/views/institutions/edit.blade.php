@extends('layouts.app')

@section('content')
<table width="100%">
  <tr>
    <td align="center"><div style="display:none; color:red;" id="notice"></div></td>
  </tr>
</table>
<v-app institutionform>
  <table width="100%">
    <tr>
      <td width="53%" valign="top">
        <v-expansion-panels multiple focusable :value="[0]">
          <v-expansion-panel>
            <v-expansion-panel-header>
              <h4>Settings for : {{ $institution->name }}</h4>
            </v-expansion-panel-header>
            <v-expansion-panel-content>
              <institution-form :institution="{{ json_encode($institution) }}"
                                :providers="{{ json_encode($providers) }}"
                                :types="{{ json_encode($types) }}"
                                :inst_groups="{{ json_encode($inst_groups) }}"
                                :all_groups="{{ json_encode($all_groups) }}"
              ></institution-form>
            </v-expansion-panel-content>
          </v-expansion-panel>
          @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
          <v-expansion-panel>
            <v-expansion-panel-header>
              <h4>User Accounts for : {{ $institution->name }}</h4>
            </v-expansion-panel-header>
            <v-expansion-panel-content>
              <div>
                <br />
                <v-btn small color="primary" type="button" href="{{ route('users.create') }}">
                  Create New User
                </v-btn>
              </div>
              <users-by-inst :users="{{ json_encode($institution->users) }}"></users-by-inst>
            </v-expansion-panel-content>
          </v-expansion-panel>
          @endif
        </v-expansion-panels>
      </td>
      <td width="2%">&nbsp;</td>
      <td width="45%" valign="top">
        <v-expansion-panels multiple focusable :value="[0]">
          <v-expansion-panel>
            <v-expansion-panel-header>
              <h4>Sushi Settings for : {{ $institution->name }}</h4>
            </v-expansion-panel-header>
            <v-expansion-panel-content>
              <sushi-by-prov :inst_id="{{ $institution->id }}"
                             :providers="{{ json_encode($providers) }}"
              ></sushi-by-inst>
            </v-expansion-panel-content>
          </v-expansion-panel>
        </v-expansion-panels>
      </td>
    </tr>
  </table>
</v-app>
@endsection
