@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Reports Dashboard</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                    @if ( Auth::user()->hasRole("Admin") )
                        I See You're an Admin!
                    @elseif ( Auth::user()->hasRole("Manager") )
                        I See You're a Manager!
                    @else
                        You are logged in as a user.
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
