<h1>New CC-Plus user credential</h1>
<p>
  @if (strlen($user_data['name'])>0)
    Hello {{ $user_data['name']}},<br />
  @else
    Hello,<br />
  @endif
  A new account user account for CC-Plus has been created for you.
</p>
<p> To login, connect your browser to <a href="{{ route('index') }}">the CC-Plus Server</a>,<br/>
@if (strlen($consortium)>0)
    Choose "{{ $consortium }}" from the "Select a Consortium" dropdown options,<br />
@endif
Enter your email address, and then enter:<br />
{{ $user_data['password'] }}
<p>
  Once you're logged in, your first task should be to reset your password. You can do this easily<br />
  by navigating to your Profile using the links connected to your name found at the top-right of<br />
  of the homepage.
</p>
