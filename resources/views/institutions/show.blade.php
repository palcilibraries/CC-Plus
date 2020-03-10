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

<v-expansion-panels multiple focusable :value="[0]">

  <v-expansion-panel class="details">
    <v-expansion-panel-header>
	<h2 class="section-title">Details</h2>
	<a href="#" class="section-action">edit</a> <em>can we make this swap in the edit view?</em>
    </v-expansion-panel-header>
    <v-expansion-panel-content>
	<div class="form-group">
	    <strong>Type:</strong>
	    {{ $institution->institutiontype->name }}
	</div>
	<div class="form-group">
	  <strong>Groups:</strong>
	  @foreach($groups as $group_id => $group_name)
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
      </v-expansion-panel-content>
    </v-expansion-panel>

          @if ( auth()->user()->hasAnyRole(['Admin','Manager']) )
          <v-expansion-panel  class="users">
            <v-expansion-panel-header>
	<h2 class="section-title">Users</h2>
	<v-btn small color="primary" type="button" href="{{ route('users.create') }}" class="section-action">add new</v-btn>
    </v-expansion-panel-header>
    <v-expansion-panel-content>
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
      </v-expansion-panel-content>
    </v-expansion-panel>
    @endif

          <v-expansion-panel class="related-list">
            <v-expansion-panel-header>
	<h2 class="section-title">Providers</h2>
	<a href="#" class="section-action">add new</a>
    </v-expansion-panel-header>
    <v-expansion-panel-content>
	
	<div>[connect provider dropdown - shows form when selected]</div>
	
	<strong style="color:red;">grab list of only existing providers who are not yet connected to this institution</strong>
    <sushi-by-prov :inst_id="{{ $institution->id }}"
                       :providers="{{ json_encode($providers) }}"
        ></sushi-by-inst>
      </v-expansion-panel-content>
    </v-expansion-panel>
	
	<div class="provider-list">
		<em>list of connected providers here</em>
	</div>

</v-expansion-panels>

</v-app>


@endsection
