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
                         :user_roles="{{ json_encode($user_roles) }}"
                         :institutions="{{ json_encode($institutions) }}"
                         :manager="{{ auth()->user()->hasRole("Manager") }}"
                         :admin="{{ auth()->user()->hasRole("Admin") }}"
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
