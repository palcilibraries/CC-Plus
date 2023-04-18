@extends('layouts.login')
@push('scripts')
<script>
  // Clear any existing data in the datastore
  window.localStorage.clear();
</script>
@endpush
@section('content')
<?php
// Pull the list of consortia from the database for the dropdown
//
use App\Consortium;
$consortia = Consortium::where('is_active',true)->get();
if ( sizeof($consortia) == 0 ) {
  echo ('<h1>No Active Consortia!</h1><br /><p>The global database is the prime suspect</p>');
  exit();
}

// Allow a consortia key as an input variable (value is ignored)
//   as in http://.../login?CCP_KEY_VALUE
// will preset the form to authenticate against the named consortium,
// and keep the select box from being displayed
//
$preset_key = "";
$preset_name = "";
if ( sizeof(request()->query()) > 0 ) {
  $input_key = array_key_first(request()->query());
  foreach ( $consortia as $con) {
    if ( $con->ccp_key == $input_key) {
      $preset_key = $input_key;
      $preset_name = $con->name;
    }
  }
}

// If only one active consortia, force it as the $preset_key
// (will override any preset attempted in the URI)
//
if ($consortia->count() == 1 ) {
  $preset_key = $consortia[0]->ccp_key;
  $preset_name = $consortia[0]->name;
}
?>
<div class="loginBox" id="app">
  <form class="login-form" method="POST" action="{{ route('login') }}" >
    @csrf
    <div class="img-top" no-gutters>
      <img src="/images/CC_Plus_Logo.png" alt="CC plus" height="50px" width="103px" />
    </div>
    <div class="login-form-fields" no-gutters>
    <v-row class="d-flex mt-4" no-gutters>
      <v-col class="d-flex pa-0 justify-start" cols="12">
        @if( $preset_key == "" )
          <select class="form-control" name="consortium" autofocus dense>
            <option value="">Select a Consortium</option>
            @foreach($consortia as $con)
              <option value="{{$con->ccp_key}}">{{$con->name}}</option>
            @endforeach
          </select>
        @else
          {{ Form::Label('consortium', 'Logging into Consortium: ' . $preset_name) }}
          <input type='hidden' name='consortium' value='{{ $preset_key }}' />
        @endif
      </v-col>
    </v-row>
    <v-row class="d-flex mt-4" no-gutters>
      <v-col class="d-flex pa-2 justify-start" cols="12">
        <clear-input inline-template>
          <div class="input-group">
            <input id="email" type="text" class="form-control" name="email" ref="email" value="{{ old('email') }}"
                   required autocomplete="email" placeholder="Email address" aria-label="Email" aria-describedby="email-addon">
            <span title="Clear" alt="Clear" @click="clearInput('email')" class="input-group-text" id="email-addon"
                  style="border-left: none; border-top-left-radius: 0; border-bottom-left-radius: 0;">
              <i class="mdi size-16 mdi-close-circle-outline"></i>
            </span>
            @error('email')
              <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
              </span>
            @enderror
          </div>
        </clear-input>
      </v-col>
    </v-row>
    <v-row class="d-flex mt-4" no-gutters>
      <v-col class="d-flex pa-2 justify-start" cols="12">
        <password-visibility inline-template>
          <div class="input-group">
            <input id="password" type="password" ref="password" class="form-control" name="password" placeholder="Password"
                   required autocomplete="current-password" aria-label="Password" aria-describedby="password-addon">
            <span :title="!visible ? 'show password?' : 'hide password?'" @click="toggleVisibility"
                  class="input-group-text" id="password-addon"
                  style="border-left: none; border-top-left-radius: 0; border-bottom-left-radius: 0;">
              <i class="mdi size-16" :class="[!visible ? 'mdi-eye' : 'mdi-eye-off']"></i>
            </span>
            @error('password')
              <span class="invalid-feedback" role="alert">
                  <strong>{{ $message }}</strong>
              </span>
            @enderror
          </div>
        </password-visibility>
      </v-col>
    </v-row>
    <v-row class="d-flex mt-4 align-center" no-gutters>
      <v-col class="d-flex justify-space-between">
        <v-btn small class="btn login-primary" type="submit">{{ __('Login') }}</v-btn>
      </v-col>
      <v-col class="d-flex justify-space-between">
        @if (Route::has('password.forgot.get'))
            <a href="{{ route('password.forgot.get') }}">
              <v-btn small class="btn">{{ __('Forgot Your Password?') }}</v-btn>
            </a>
        @endif
      </v-col>
    </v-row>
    </div>
  </form>
</div>
@if (Session::has('error'))
  <div class="login-errors" no-gutters>
    <span class="d-flex mx-1 my-2 text-danger">{{ Session::get('error') }}</span>
  </div>
@endif
@endsection
