@extends('layouts.app')

@section('content')
<v-app institutionform>

<div>
	<div class="page-header">
	    <h1>{{ $institution->name }}</h1>
	</div>
	<div class="page-action">
	<!--            <a class="btn btn-primary" href="{{ route('institutions.index') }}"> Back</a>-->
		<a class="btn btn-primary" href="#">Delete</a>
	</div>
</div>

  <div class="details">
	<h2 class="section-title">Details</h2>
	<a href="#" class="section-action">edit</a> <em>can we make this swap in the edit view?</em>
	<div class="form-group">
	    <strong>Type:</strong>
	    {{ $institution->institutiontype->name }}
	</div>
	<div class="form-group">
	  <strong>Groups:</strong>
	  @foreach($inst_groups as $group_id => $group_name)
	     @if($institution->isAMemberof($group_id))
	        <label class="badge badge-success">{{ $group_name }} </label>
	     @endif
	  @endforeach
	</div>
	<div class="form-group">
	    <strong>FTE:</strong>
	    {{ $institution->fte }}
	</div>
	<div class="form-group">
	    <strong>Visibility:</strong>
	    <em>placeholder</em>
	</div>
	<div class="form-group">
	    <strong>Status:</strong>
	    {{ $institution->is_active ? 'Active' : 'Inactive' }}
	</div>
	<div class="form-group">
	    <strong>Notes:</strong>
	    {{ $institution->notes }}
	</div>
	
    <institution-form :institution="{{ json_encode($_inst) }}"
                      :providers="{{ json_encode($providers) }}"
                      :types="{{ json_encode($types) }}"
                      :inst_groups="{{ json_encode($inst_groups) }}"
                      :all_groups="{{ json_encode($all_groups) }}"
	></institution-form>
    </div>

          @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
          <div class="users">
	<h2 class="section-title">Users</h2>
	<v-btn small color="primary" type="button" href="{{ route('users.create') }}" class="section-action">add new</v-btn>
	<em>associated user list here</em>
    <v-simple-table :dense="false">
      <template v-slot:default>
        <thead>
          <tr>
            <th class="text-left">Name</th>
            <th class="text-left">Permission level</th>
            <th class="text-left">Last Login</th>
            <th class="text-left">&nbsp;</th>
          </tr>
        <thead>
        <tbody>
        @foreach ($institution->users as $key => $user)
          <tr>
            <td><a href="{{ route('users.edit',$user->id) }}">{{ $user->name }}</td>
            <td>
              @foreach($user->roles as $r)
                <v-chip>{{ $r->name }}</v-chip>
              @endforeach
            </td>
            <td>{{ $user->last_login }}</td>
            <td>
				<strong style="color:red;">make this an edit link instead of delete</strong>
              {!! Form::open(['method' => 'DELETE','route' => ['users.destroy', $user->id],'style'=>'display:inline']) !!}
                {!! Form::submit('Delete', ['class' => 'btn btn-danger']) !!}
              {!! Form::close() !!}
            </td>
          </tr>
        @endforeach
        </tbody>
      </template>
    </v-simple-table>
    </div>
    @endif

          <div class="related-list">
	<h2 class="section-title">Providers</h2>
	<a href="#" class="section-action">add new</a>
	
	<div>[connect provider dropdown - shows form when selected]</div>
	
	<strong style="color:red;">grab list of only existing providers who are not yet connected to this institution</strong>
    <sushi-by-prov :inst_id="{{ $institution->id }}"
                       :providers="{{ json_encode($providers) }}"
        ></sushi-by-inst>
    
	<div class="provider-list">
		<em>list of connected providers here</em>
	</div>
	
	</div>

</v-app>


@endsection
