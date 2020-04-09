@extends('layouts.app')

@section('content')
<v-app institutionform>
  <table width="100%">
    <tr>
      <td width="49%" valign="top">
        <v-expansion-panels multiple focusable :value="[0]">
          <v-expansion-panel>
            <v-expansion-panel-header>
              <h4>Settings for : {{ $user->name }}</h4>
            </v-expansion-panel-header>
            <v-expansion-panel-content>
              <user-form :user="{{ json_encode($user) }}"
                         :roles="{{ json_encode($roles) }}"
                         :institutions="{{ json_encode($institutions) }}"
              ></user-form>
            </v-expansion-panel-content>
          </v-expansion-panel>
        </v-expansion-panels>
      </td>
      <td width="2%">&nbsp;</td>
      <td width="49%" valign="top">&nbsp;</td>
  </tr>
  </table>
</v-app>
@endsection
