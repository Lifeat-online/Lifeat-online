<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Redirecting to PayFast</title>
</head>
<body>
    <p>Redirecting to PayFast.</p>
    <form id="payfast-form" action="{{ $paymentUrl }}" method="post">
        @foreach ($paymentFields as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <button type="submit">Continue to PayFast</button>
    </form>
    <script>
        window.setTimeout(() => document.getElementById('payfast-form').submit(), 200);
    </script>
</body>
</html>
