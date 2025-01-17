<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the default time zone at the beginning of the script
date_default_timezone_set('Asia/Kuala_Lumpur');

$host = 'project-db.cuiufzyofhu0.us-east-1.rds.amazonaws.com';
$user = 'main';
$password = 'project-password';
$database = 'project-db';

try {
    // Create a new PDO instance
    $connection = new PDO("mysql:host=$host", $user, $password);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create the database if it doesn't exist
    $createDbQuery = "CREATE DATABASE IF NOT EXISTS $database";
    $connection->exec($createDbQuery);

    // Use the newly created database
    $connection->exec("USE $database");

    // Create the products table if it doesn't exist
    $createProductsTableQuery = "CREATE TABLE IF NOT EXISTS products (
        id INT PRIMARY KEY NOT NULL,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        quantity INT NOT NULL,
        cost DECIMAL(10, 2) NOT NULL,
        type VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB";

    $connection->exec($createProductsTableQuery);

    // Create the sales table if it doesn't exist
    $createSalesTableQuery = "CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT,
        quantity INT NOT NULL,
        sale_date DATE NOT NULL,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB";

    $connection->exec($createSalesTableQuery);

    // Create the historical_sales table if it doesn't exist
    $createHistoricalSalesTableQuery = "CREATE TABLE IF NOT EXISTS historical_sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        original_product_id INT,
        product_name VARCHAR(255),
        quantity INT NOT NULL,
        sale_date DATE NOT NULL,
        cost DECIMAL(10, 2),
        price DECIMAL(10, 2)
    ) ENGINE=InnoDB";

    $connection->exec($createHistoricalSalesTableQuery);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] == 'add') {
                $id = $_POST['id'];
                $name = $_POST['name'];
                $cost = $_POST['cost'];
                $price = $_POST['price'];
                $quantity = $_POST['quantity'];
                $type = $_POST['type'];

                // Error checking for blank fields
                if (empty($id) || empty($name) || empty($cost) || empty($price) || empty($quantity) || empty($type)) {
                    echo "All fields are required.";
                } elseif (!is_numeric($id) || $id <= 0) {
                    echo "Invalid product ID. It must be a positive number.";
                } elseif (!is_numeric($cost) || $cost <= 0) {
                    echo "Invalid cost. It must be a positive number greater than zero.";
                } elseif (!is_numeric($price) || $price <= 0) {
                    echo "Invalid price. It must be a positive number greater than zero.";
                } elseif (!is_numeric($quantity) || $quantity <= 0 || floor($quantity) != $quantity) {
                    echo "Invalid quantity. It must be a positive integer.";
                } elseif ($price <= $cost) {
                    echo "Price must be greater than the cost.";
                } else {
                    // Check if product with this ID already exists
                    $stmt_check = $connection->prepare("SELECT COUNT(*) AS count FROM products WHERE id=:id");
                    $stmt_check->bindParam(':id', $id);
                    $stmt_check->execute();
                    $result_check = $stmt_check->fetch(PDO::FETCH_ASSOC);

                    if ($result_check['count'] > 0) {
                        echo "Product with ID $id already exists";
                    } else {
                        // Insert a new product into the products table
                        $stmt = $connection->prepare("INSERT INTO products (id, name, cost, price, quantity, type) VALUES (:id, :name, :cost, :price, :quantity, :type)");
                        $stmt->bindParam(':id', $id);
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':cost', $cost);
                        $stmt->bindParam(':price', $price);
                        $stmt->bindParam(':quantity', $quantity);
                        $stmt->bindParam(':type', $type);
                        $stmt->execute();

                        echo "Product added successfully";
                    }
                }
            } elseif ($_POST['action'] == 'update') {
                $id = $_POST['id'];
                $name = $_POST['name'];
                $cost = $_POST['cost'];
                $price = $_POST['price'];
                $quantity = $_POST['quantity'];
                $type = $_POST['type']; 
                // Error checking for blank fields
                if (empty($id) || empty($name) || empty($cost) || empty($price) || empty($quantity) || empty($type)) {
                    echo "All fields are required.";
                } elseif (!is_numeric($id) || $id <= 0) {
                    echo "Invalid product ID. It must be a positive number.";
                } elseif (!is_numeric($cost) || $cost <= 0) {
                    echo "Invalid cost. It must be a positive number greater than zero.";
                } elseif (!is_numeric($price) || $price <= 0) {
                    echo "Invalid price. It must be a positive number greater than zero.";
                } elseif (!is_numeric($quantity) || $quantity <= 0 || floor($quantity) != $quantity) {
                    echo "Invalid quantity. It must be a positive integer.";
                } elseif ($price <= $cost) {
                    echo "Price must be greater than the cost.";
                } else {
                    // Check if the product with this ID exists
                    $stmt_check = $connection->prepare("SELECT COUNT(*) AS count FROM products WHERE id=:id");
                    $stmt_check->bindParam(':id', $id);
                    $stmt_check->execute();
                    $result_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
                    if ($result_check['count'] > 0) {
                        // Product exists, proceed with update
                        $connection->beginTransaction();
            
                        try {
                            // Move existing sales records to historical_sales table
                            $stmt_move_sales = $connection->prepare("
                                INSERT INTO historical_sales (original_product_id, product_name, quantity, sale_date, cost, price)
                                SELECT s.product_id, p.name, s.quantity, s.sale_date, p.cost, p.price
                                FROM sales s
                                JOIN products p ON s.product_id = p.id
                                WHERE s.product_id = :id
                            ");
                            $stmt_move_sales->bindParam(':id', $id);
                            $stmt_move_sales->execute();
            
                            // Delete existing sales records
                            $stmt_delete_sales = $connection->prepare("DELETE FROM sales WHERE product_id = :id");
                            $stmt_delete_sales->bindParam(':id', $id);
                            $stmt_delete_sales->execute();
            
                            // Update the product
                            $stmt = $connection->prepare("UPDATE products SET name=:name, cost=:cost, price=:price, quantity=:quantity, type=:type WHERE id=:id");
                            $stmt->bindParam(':id', $id);
                            $stmt->bindParam(':name', $name);
                            $stmt->bindParam(':cost', $cost);
                            $stmt->bindParam(':price', $price);
                            $stmt->bindParam(':quantity', $quantity);
                            $stmt->bindParam(':type', $type);
                            $stmt->execute();
            
                            $connection->commit();
            
                            // Check if any rows were affected
                            if ($stmt->rowCount() > 0) {
                                echo "Product updated successfully";
                            } else {
                                echo "No changes made";
                            }
                        } catch (Exception $e) {
                            $connection->rollBack();
                            echo "Error occurred: " . $e->getMessage();
                        }
                    } else {
                        echo "Product with ID $id does not exist";
                    }
                }
            } elseif ($_POST['action'] == 'delete') {
                $id = $_POST['id'];
                
                // Error checking for ID to ensure it's a number
                if (empty($id)) {
                    echo "Product ID is required.";
                } elseif (!is_numeric($id) || $id <= 0) {
                    echo "Invalid product ID. It must be a positive number.";
                } else {
                    // Check if product with this ID exists
                    $stmt_check = $connection->prepare("SELECT COUNT(*) AS count FROM products WHERE id=:id");
                    $stmt_check->bindParam(':id', $id);
                    $stmt_check->execute();
                    $result_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result_check['count'] > 0) {
                        // Product exists, proceed with deletion
                        $connection->beginTransaction();
                        
                        try {
                            // Move sales records to historical_sales table
                            $stmt_move_sales = $connection->prepare("
                                INSERT INTO historical_sales (original_product_id, product_name, quantity, sale_date, cost, price)
                                SELECT s.product_id, p.name, s.quantity, s.sale_date, p.cost, p.price
                                FROM sales s
                                JOIN products p ON s.product_id = p.id
                                WHERE s.product_id = :id
                            ");
                            $stmt_move_sales->bindParam(':id', $id);
                            $stmt_move_sales->execute();

                            // Delete the product (this will cascade delete the sales records)
                            $stmt_delete = $connection->prepare("DELETE FROM products WHERE id = :id");
                            $stmt_delete->bindParam(':id', $id);
                            $stmt_delete->execute();
                            
                            $connection->commit();
                            echo "Product deleted successfully.";
                        } catch (Exception $e) {
                            $connection->rollBack();
                            echo "Error occurred: " . $e->getMessage();
                        }
                    } else {
                        // Product does not exist
                        echo "Product not found";
                    }
                }
            } elseif ($_POST['action'] == 'checkout') {
                $id = $_POST['id'];
                $quantity = $_POST['quantity'];

                // Start a database transaction
                $connection->beginTransaction();
        
                try {
                    // Check if the product with this ID exists and has sufficient quantity
                    $stmt_check = $connection->prepare("SELECT quantity FROM products WHERE id = :product_id");
                    $stmt_check->bindParam(':product_id', $id, PDO::PARAM_INT);
                    $stmt_check->execute();
                    $product = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
                    if ($product && $product['quantity'] >= $quantity) {
                        // Update product quantity after checkout
                        $stmt = $connection->prepare("UPDATE products SET quantity = quantity - :purchased_quantity WHERE id = :id");
                        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                        $stmt->bindParam(':purchased_quantity', $quantity, PDO::PARAM_INT);
                        $stmt->execute();
                        // Inside the 'checkout' action, after updating the product quantity
                        $sale_date = (new DateTime())->format('Y-m-d'); // Current date
                        $stmt = $connection->prepare("INSERT INTO sales (product_id, quantity, sale_date) VALUES (:product_id, :quantity, :sale_date)");
                        $stmt->bindParam(':product_id', $id);
                        $stmt->bindParam(':quantity', $quantity);
                        $stmt->bindParam(':sale_date', $sale_date);
                        $stmt->execute();
        
                        // Commit the transaction if the update is successful
                        $connection->commit();
                        echo "Product checked out successfully";
                        } else {
                            echo "Insufficient quantity or product not found";
                        }
                    } catch (PDOException $e) {
                        // Roll back the transaction if any error occurs
                        $connection->rollBack();
                        echo "Error checking out product: " . $e->getMessage();
                    }
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action'])) {
        if ($_GET['action'] == 'view') {
            // Fetch all products from the products table
            $stmt = $connection->prepare("SELECT * FROM products");
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($products);
        } elseif ($_GET['action'] == 'get') {
            $id = $_GET['id'];

            // Error checking for ID to ensure it's a number
            if (!is_numeric($id) || $id <= 0) {
                echo "Invalid product ID. It must be a positive number.";
                return;
            }

            // Fetch the product details based on the provided ID
            $stmt = $connection->prepare("SELECT * FROM products WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            // Return the product details as JSON
            echo json_encode($product);
        } elseif ($_GET['action'] == 'getSales') {
            $startDate = $_GET['startDate'];
            $endDate = $_GET['endDate'];
        
            // Fetch sales data
            $stmt = $connection->prepare("
                (SELECT p.name as product_name, p.cost, p.price, SUM(s.quantity) as quantity_sold
                FROM sales s
                JOIN products p ON s.product_id = p.id
                WHERE s.sale_date BETWEEN :startDate AND :endDate
                GROUP BY p.id, p.name, p.cost, p.price)
                UNION ALL
                (SELECT product_name, cost, price, SUM(quantity) as quantity_sold
                FROM historical_sales
                WHERE sale_date BETWEEN :startDate AND :endDate
                GROUP BY original_product_id, product_name, cost, price)
            ");
            $stmt->bindParam(':startDate', $startDate);
            $stmt->bindParam(':endDate', $endDate);
            $stmt->execute();
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
            echo json_encode($sales);
        } elseif ($_GET['action'] == 'getDailyProfits') {
            $startDate = $_GET['startDate'];
            $endDate = $_GET['endDate'];
        
            // Fetch daily profit data for the month
            $stmt = $connection->prepare("
                SELECT DATE(sale_date) as date, SUM((price - cost) * quantity) as profit
                FROM (
                    SELECT s.sale_date, p.price, p.cost, s.quantity
                    FROM sales s
                    JOIN products p ON s.product_id = p.id
                    WHERE s.sale_date BETWEEN :startDate AND :endDate
                    UNION ALL
                    SELECT sale_date, price, cost, quantity
                    FROM historical_sales
                    WHERE sale_date BETWEEN :startDate AND :endDate
                ) as combined_sales
                GROUP BY DATE(sale_date)
                ORDER BY DATE(sale_date)
            ");
            $stmt->bindParam(':startDate', $startDate);
            $stmt->bindParam(':endDate', $endDate);
            $stmt->execute();
            $dailyProfits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
            echo json_encode($dailyProfits);
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
