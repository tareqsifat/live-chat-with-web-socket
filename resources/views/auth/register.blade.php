<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
</head>
<body>
    <h1>Register</h1>
    <form method="POST" action="/register">
        @csrf
        <label>Name:</label><input name="name" required><br>
        <label>Email:</label><input name="email" type="email" required><br>
        <label>Password:</label><input name="password" type="password" required><br>
        <label>Confirm Password:</label><input name="password_confirmation" type="password" required><br>
        <button type="submit">Register</button>
    </form>
</body>
</html>
