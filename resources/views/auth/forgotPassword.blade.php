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

// If only one active consortia, force it using $preset_key
$preset_key = ($consortia->count()==1 ) ? $consortia[0]->ccp_key : "";
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Login') }}</div>
                <div class="card-body">
                    @if (Session::has('message'))
                         <div class="alert alert-success" role="alert">
                            {{ Session::get('message') }}
                        </div>
                    @endif
                    <form method="POST" action="{{ route('password.forgot.post') }}">
                        @csrf
                        @if( $preset_key == "" )
                        <div class="form-group">
                          {{ Form::Label('consortium', 'Consortium:') }}
                          <select class="form-control" name="consortium" required autofocus>
                            <option value="">Choose your Consortium</option>
                            @foreach($consortia as $con)
                              <option value="{{$con->ccp_key}}">{{$con->name}}</option>
                            @endforeach
                          </select>
                        </div>
                        @else
                        <div class="form-group">
                          <input type='hidden' name='consortium' value='{{ $preset_key }}' />
                        </div>
                        @endif

                        <div class="form-group row">
                            <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('E-Mail Address') }}</label>
                            <div class="col-md-6">
                                <input id="email" type="text" class="form-control" name="email" value="{{ old('email') }}" required autocomplete="email">
                                @if ($errors->has('email'))
                                    <span class="text-danger">{{ $errors->first('email') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6 offset-md-4">
                            <button type="submit" class="btn btn-primary">
                                Send Reset Password Link
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
