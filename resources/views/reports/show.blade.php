@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12 margin-tb">
        <div class="pull-left">
            <h2> Show Report</h2>
        </div>
        <div class="pull-right">
            <a class="btn btn-primary" href="{{ route('reports.index') }}"> Back</a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Name:</strong>
            {{ $report->name }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Description:</strong>
            {{ $report->legend }}
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
          @if ($report->parent_id == 0)
            <strong>Master report for</strong> :
            @foreach ($report->children as $child)
              <a href="{{ URL::route('reports.show',$child->id) }}">{{ $child->name }} (r{{ $child->revision }})</a>
              &nbsp; &nbsp; &nbsp;
            @endforeach
          @else
            <strong>Sub-Report of</strong> :
            <a href="{{ URL::route('reports.show',$report->parent->id) }}">
              {{ $report->parent->name }} (r{{ $report->parent->revision }})</a>
          @endif
        </div>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-12">
        <div class="form-group">
            <strong>Fields:</strong>
            <?php $_n=0; ?>
            @foreach ($fields as $field)
               {{ $field->legend }}
               @if ($field->is_alertable)
                 &nbsp; (alertable)
               @endif
               &nbsp; &nbsp; &nbsp;
               <?php
                 $_n++;
                 if ($_n > 3) {
                   echo '<br />';
                   $_n = 0;
                 }
               ?>
            @endforeach
        </div>
    </div>
</div>
@endsection
