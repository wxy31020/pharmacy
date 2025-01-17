<?php
    session_start();

    $host = 'project-db.c3lmmtyj2ubz.us-east-1.rds.amazonaws.com';
    $user = 'main';
    $password = 'project-password';
    $database = 'project';

    try {
        // Create a new PDO instance
        $connection = new PDO("mysql:host=$host", $user, $password);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create the database if it doesn't exist
        $createDbQuery = "CREATE DATABASE IF NOT EXISTS $database";
        $connection->exec($createDbQuery);

        // Use the newly created database
        $connection->exec("USE $database");

        // Create the users table if it doesn't exist
        $createTableQuery = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $connection->exec($createTableQuery);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $action = $_POST['action'];
            $username = $_POST['username'];
            $password = $_POST['password'];

            if ($action == 'register') {
                // Check if the username already exists
                $stmt = $connection->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $userCount = $stmt->fetchColumn();

                if ($userCount > 0) {
                    echo "Error: Username already exists";
                } else {
                    // Register a new user
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $connection->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password', $hashedPassword);

                    if ($stmt->execute()) {
                        echo "User registered successfully";
                    } else {
                        echo "Error registering user";
                    }
                }
            } elseif ($action == 'login') {
                // Log in an existing user
                $stmt = $connection->prepare("SELECT password FROM users WHERE username = :username");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result && password_verify($password, $result['password'])) {
                    $_SESSION['username'] = $username;
                    echo "Login successful";
                } else {
                    echo "Invalid username or password";
                }
            }
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
?>
