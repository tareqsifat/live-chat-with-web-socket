<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h1>Login</h1>
    <form method="POST" action="/login">
        @csrf
        <label>Email:</label><input name="email" type="email" required><br>
        <label>Password:</label><input name="password" type="password" required><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
