@extends('layouts.app')
@section('content')
<div class="col-lg-12 margin-tb">
  <a href="{{ url()->previous() }}"><< Back</a>
</div>
<div class="container">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            <div class="panel panel-default">
                <div class="panel-heading"><h3>Consortia</h3></div>
                <ul>
                    @foreach ($consortia as $consortium)
                        <li>
                            <a href="/consortia/{{ $consortium->id }}">{{ $consortium->name }}</a>
            I           </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
