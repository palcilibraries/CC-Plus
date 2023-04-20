@extends('layouts.app')
@section('content')
<view-reports :counter_reports="{{ json_encode($counter_reports) }}"></view-reports>
@endsection
