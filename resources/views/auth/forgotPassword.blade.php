@extends('layouts.login')
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

// If only one active consortia, force it using $preset_key
$preset_key = ($consortia->count()==1 ) ? $consortia[0]->ccp_key : "";
?>

<div class="loginBox" id="app">
  <form class="login-form" method="POST" action="{{ route('password.forgot.post') }}">
  @csrf
  <div class="img-top" no-gutters>
    <img src="/images/CC_Plus_Logo.png" alt="CC plus" height="50px" width="103px" />
  </div>
  <div class="login-form-fields" no-gutters>
    <v-row class="d-flex mt-4" no-gutters>
      <v-col class="d-flex pa-0 justify-start" cols="12">
        @if ( $preset_key == "" )
          <select class="form-control" name="consortium" required autofocus dense>
            <option value="">Select your Consortium</option>
            @foreach($consortia as $con)
              <option value="{{$con->ccp_key}}">{{$con->name}}</option>
            @endforeach
          </select>
        @else
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
    <v-row class="d-flex mt-4 align-center" no-gutters>
      <v-col class="d-flex justify-center">
        <v-btn small class="btn login-primary" type="submit">Send Reset Password Link</v-btn>
      </v-col>
    </v-row>
  </div>
</div>
@if (Session::has('error'))
  <div class="login-notices" no-gutters>
    <span class="d-flex mx-1 my-2 text-danger">{{ Session::get('error') }}</span>
  </div>
@endif
@if (Session::has('message'))
  <div class="login-notices" no-gutters>
    <span class="d-flex mx-1 my-2 alert alert-success">{{ Session::get('message') }}</span>
  </div>
@endif
@endsection
