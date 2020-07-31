<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@4.x/css/materialdesignicons.min.css" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('/css/app.css') }}">
</head>

<body class="container-fluid">
    <div id="app">
        <!-- Skip <topnav> if auth()->user() is undefined... -->
        @if ( auth()->check() )
        <topnav :user="{{ json_encode(App\User::with('roles')->where('id',auth()->id())->first()->toArray()) }}"
        ></topnav>
        @endif
        <main class="py-4">
            @yield('content')
        </main>
    </div>
    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}"></script>
    <script type="text/javascript">
      (function() {
          var s = document.createElement("script");
          s.type = "text/javascript";
          s.async = true;
          s.src = '//api.usersnap.com/load/e3380418-7c3b-4537-8023-dd1f9dcd4899.js';
          var x = document.getElementsByTagName('script')[0];
          x.parentNode.insertBefore(s, x);
      })();
    </script>
</body>
</html>
