<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Crypto Portfolio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }
        a {
            text-decoration: none;
            color: blue;
        }
        .btn {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #ccc;
            background: #f5f5f5;
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }
        .success {
            background: #e6ffed;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #b7ebc6;
        }
        .error {
            background: #fff2f0;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ffccc7;
        }
    </style>
</head>
<body>
    <h1>仮想通貨ポートフォリオ管理</h1>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</body>
</html>