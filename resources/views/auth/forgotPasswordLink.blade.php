@extends('layouts.login')
@section('content')
<div class="loginBox" id="app">
  <form class="login-form" method="POST" action="{{ route('password.reset.post') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <input type="hidden" name="consortium" value="{{ $consortium }}">
    <div class="img-top" no-gutters>
      <img src="/images/CC_Plus_Logo.png" alt="CC plus" height="50px" width="103px" />
    </div>
    <div class="login-form-fields" no-gutters>
      <v-row class="d-flex mt-4" no-gutters>
        <v-col class="d-flex pa-2 justify-start" cols="12">
          <clear-input inline-template>
            <div class="input-group">
              <input id="email" type="text" class="form-control" name="email" ref="email" value="{{ old('email') }}" required
                    autofocus autocomplete="email" placeholder="Email address" aria-label="Email" aria-describedby="email-addon">
              <span title="Clear" alt="Clear" @click="clearInput('email')" class="input-group-text" id="email-addon"
                    style="border-left: none; border-top-left-radius: 0; border-bottom-left-radius: 0;">
                <i class="mdi size-16 mdi-close-circle-outline"></i>
              </span>
              @if ($errors->has('password'))
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $errors->first('email') }}</strong>
                </span>
              @endif
            </div>
          </clear-input>
        </v-col>
      </v-row>
      <v-row class="d-flex mt-4" no-gutters>
        <v-col class="d-flex pa-2 justify-start" cols="12">
          <password-visibility inline-template>
            <div class="input-group">
              <input id="password" type="password" ref="password" class="form-control" name="password" placeholder="Password"
                    required autofocus autocomplete="current-password" aria-label="Password" aria-describedby="password-addon">
              <span :title="!visible ? 'show password?' : 'hide password?'" @click="toggleVisibility('password')"
                    class="input-group-text" id="password-addon"
                    style="border-left: none; border-top-left-radius: 0; border-bottom-left-radius: 0;">
                <i class="mdi size-16" :class="[!visible ? 'mdi-eye' : 'mdi-eye-off']"></i>
              </span>
              @if ($errors->has('password'))
                <span class="invalid-feedback" role="alert">
                  <strong>{{ $errors->first('password') }}</strong>
                </span>
              @endif
            </div>
          </password-visibility>
        </v-col>
      </v-row>
      <v-row class="d-flex mt-4" no-gutters>
        <v-col class="d-flex pa-2 justify-start" cols="12">
          <password-visibility inline-template>
            <div class="input-group">
              <input id="password_confirmation" type="password" ref="confirmpass" class="form-control" name="password_confirmation"
                     placeholder="Confirm Password" required autofocus autocomplete="current-confirmpass"
                     aria-label="Confirm Password" aria-describedby="confirmpass-addon">
              <span :title="!visible ? 'show password?' : 'hide password?'" @click="toggleVisibility('confirmpass')"
                    class="input-group-text" id="confirmpass-addon"
                    style="border-left: none; border-top-left-radius: 0; border-bottom-left-radius: 0;">
                <i class="mdi size-16" :class="[!visible ? 'mdi-eye' : 'mdi-eye-off']"></i>
              </span>
              @if ($errors->has('confirmpass'))
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $errors->first('confirmpass') }}</strong>
                </span>
              @endif
            </div>
          </password-visibility>
        </v-col>
      </v-row>
      <v-row class="d-flex mt-4 align-center" no-gutters>
        <v-col class="d-flex justify-center">
          <v-btn small class="btn login-primary" type="submit">Reset Password</v-btn>
        </v-col>
      </v-row>
    </div>
  </form>
</div>
@endsection
