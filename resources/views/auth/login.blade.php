@extends('layouts.app')

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
if ( sizeof(request()->query()) > 0 ) {
  $input_key = array_key_first(request()->query());
  foreach ( $consortia as $con) {
    if ( $con->ccp_key == $input_key) {
      $preset_key = $input_key;
    }
  }
}

// If only one active consortia, force it as the $preset_key
// (will override any preset attempted in the URI)
//
if (sizeof($consortia) == 1 )
  $preset_key = $consortia[0]->ccp_key;
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Login') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        @if( $preset_key == "" )
                        <div class="form-group">
                          {{ Form::Label('consortium', 'Consortium:') }}
                          <select class="form-control" name="consortium" required autofocus>
                            <option value="">Select a Consortium</option>
                            @foreach($consortia as $con)
                              <option value="{{$con->ccp_key}}">{{$con->name}}</option>
                            @endforeach
                          </select>
                        </div>
                        @else
                        <div class="form-group">
                          {{ Form::Label('consortium', 'Logging into Consortium: ' . $preset_key) }}
                          <input type='hidden' name='consortium' value='{{ $preset_key }}' />
                        </div>
                        @endif

                        <div class="form-group row">
                            <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('E-Mail Address') }}</label>

                            <div class="col-md-6">
<?php
                                // <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
?>
                                <input id="email" type="text" class="form-control" name="email" value="{{ old('email') }}" required autocomplete="email">

                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="password" class="col-md-4 col-form-label text-md-right">{{ __('Password') }}</label>

                            <div class="col-md-6">
<?php
                                // <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
?>
                                <input id="password" type="password" class="form-control" name="password" required autocomplete="current-password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
<!--
                        <div class="form-group row">
                            <div class="col-md-6 offset-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="remember">{{ __('Remember Me') }}</label>
                                </div>
                            </div>
                        </div>
-->
                        <div class="form-group row mb-0">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Login') }}
                                </button>

                                @if (Route::has('password.request'))
                                    <a class="btn btn-link" href="{{ route('password.request') }}">
                                        {{ __('Forgot Your Password?') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
