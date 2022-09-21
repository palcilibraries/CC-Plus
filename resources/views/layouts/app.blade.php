<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="{!! asset('images/favicon.ico') !!}"/>
    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@4.x/css/materialdesignicons.min.css" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('/css/app.css') }}">
</head>

<body class="container-fluid">
    <div id="app" data-app>
        <!-- Skip <topnav> if auth()->user() is undefined... -->
        @if ( auth()->check() )
            @if ( auth()->user()->hasRole('SuperUser') )
                <topnav :user="{{ json_encode(App\User::with('roles')->where('id',auth()->id())->first()->toArray()) }}"
                        :consortia="{{ json_encode(\App\Consortium::get(['name','ccp_key'])->toArray() ) }}"
                        :ccp_key="{{ json_encode( Session::get('ccp_con_key') ) }}"
                ></topnav>
            @else
                <topnav :user="{{ json_encode(App\User::with('roles')->where('id',auth()->id())->first()->toArray()) }}"
                ></topnav>
            @endif
        @endif
        <main class="py-4">
            @yield('content')
        </main>
    </div>
    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
