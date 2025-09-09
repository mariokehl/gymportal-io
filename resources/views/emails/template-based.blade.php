<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - E-Mail</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .gym-logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        .email-body {
            padding: 30px;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
        }
        h1, h2, h3 {
            color: #495057;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .button:hover {
            background-color: #0056b3;
        }
        ul {
            padding-left: 20px;
        }
        li {
            margin-bottom: 5px;
        }
        hr {
            border: none;
            border-top: 1px solid #e9ecef;
            margin: 20px 0;
        }
        .text-muted {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            @if($gym->logo_path)
                <img src="{{ asset('storage/' . $gym->logo_path) }}" alt="{{ $gym->name }}" class="gym-logo">
            @endif
            <h1>{{ $gym->name }}</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            {!! $renderedContent !!}
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><strong>{{ $gym->name }}</strong></p>
            @if($gym->address)
                <p>{{ $gym->address }}<br>
                {{ $gym->postal_code }} {{ $gym->city }}</p>
            @endif
            @if($gym->phone)
                <p>Telefon: {{ $gym->phone }}</p>
            @endif
            @if($gym->email)
                <p>E-Mail: {{ $gym->email }}</p>
            @endif
            @if($gym->website)
                <p>Website: <a href="{{ $gym->website }}">{{ $gym->website }}</a></p>
            @endif
        </div>
    </div>
</body>
</html>
