<?php
session_start();
ob_start();

// Конфигурация файлов
$menuFile = 'menu.json';
$calculatorFile = 'calculator.json';
$usersFile = 'users.json';
$excelFile = 'menu.xlsx'; // Файл для экспорта Excel

// Функция для безопасной обработки данных
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Инициализация меню
if (!file_exists($menuFile)) {
    $menuItems = [];
    $categories = ['Горячее', 'Салаты', 'Напитки', 'Десерты', 'Выпечка', 'Закуски'];

    for ($i = 1; $i <= 24; $i++) {
        $menuItems[] = [
            'id' => $i,
            'name' => "Блюдо $i",
            'price' => rand(50, 500),
            'description' => "Вкусное блюдо с уникальным вкусом и свежими ингредиентами.",
            'popular' => rand(0, 1) ? true : false,
            'vegetarian' => rand(0, 1) ? true : false,
            'category' => $categories[array_rand($categories)]
        ];
    }
    file_put_contents($menuFile, json_encode($menuItems, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// Инициализация пользователей
if (!file_exists($usersFile)) {
    $users = [
        [
            'id' => 1,
            'name' => 'Администратор',
            'email' => 'admin@college.ru',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin'
        ],
        [
            'id' => 2,
            'name' => 'Модератор',
            'email' => 'moderator@college.ru',
            'password' => password_hash('moderator123', PASSWORD_DEFAULT),
            'role' => 'moderator'
        ],
        [
            'id' => 3,
            'name' => 'Пользователь',
            'email' => 'user@college.ru',
            'password' => password_hash('user123', PASSWORD_DEFAULT),
            'role' => 'user'
        ]
    ];
    file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// Загрузка данных
$menuItems = json_decode(file_get_contents($menuFile), true) ?: [];
$users = json_decode(file_get_contents($usersFile), true) ?: [];

// Инициализация калькулятора (корзины)
if (!file_exists($calculatorFile)) {
    file_put_contents($calculatorFile, json_encode([]));
}
$calculatorItems = json_decode(file_get_contents($calculatorFile), true) ?: [];

// Определение роли пользователя
$role = 'guest';
$currentUser = null;

if (isset($_SESSION['user_id'])) {
    foreach ($users as $user) {
        if ($user['id'] == $_SESSION['user_id']) {
            $currentUser = $user;
            $role = $user['role'];
            break;
        }
    }
}

// Обработка формы регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = sanitize($_POST['name']);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $passwordConfirm = $_POST['password_confirm'];

    // Валидация
    $errors = [];

    if (!$name) $errors[] = "Введите имя";
    if (!$email) $errors[] = "Неверный email";
    if (strlen($password) < 6) $errors[] = "Пароль должен быть не менее 6 символов";
    if ($password !== $passwordConfirm) $errors[] = "Пароли не совпадают";

    // Проверка уникальности email
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            $errors[] = "Email уже используется";
            break;
        }
    }

    if (empty($errors)) {
        // Создание нового пользователя
        $newUserId = count($users) + 1;
        $newUser = [
            'id' => $newUserId,
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'user' // Все новые пользователи получают роль 'user'
        ];

        $users[] = $newUser;
        file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Автоматический вход
        $_SESSION['user_id'] = $newUserId;
        $currentUser = $newUser;
        $role = 'user';

        header("Location: {$_SERVER['PHP_SELF']}");
        exit;
    }
}

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $loginError = "";

    if ($email && $password) {
        $userFound = false;
        foreach ($users as $user) {
            if ($user['email'] === $email && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $currentUser = $user;
                $role = $user['role'];
                header("Location: {$_SERVER['PHP_SELF']}");
                exit;
            }
        }
        $loginError = "Неверный email или пароль";
    } else {
        $loginError = "Введите корректные данные";
    }
}

// Обработка формы сохранения меню
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_menu'])) {
    if ($role === 'admin' || $role === 'moderator') {
        $updatedMenu = [];

        // Обработка существующих блюд
        foreach ($_POST['dish'] as $id => $dish) {
            $name = sanitize($dish['name']);
            $price = filter_var($dish['price'], FILTER_VALIDATE_FLOAT);
            $category = sanitize($dish['category']);
            $description = sanitize($dish['description']);
            $popular = isset($dish['popular']) ? true : false;
            $vegetarian = isset($dish['vegetarian']) ? true : false;

            if ($name && $price !== false) {
                $updatedMenu[] = [
                    'id' => (int)$id,
                    'name' => $name,
                    'price' => (float)$price,
                    'category' => $category ?: 'Горячее',
                    'description' => $description ?: 'Описание блюда',
                    'popular' => $popular,
                    'vegetarian' => $vegetarian
                ];
            }
        }

        // Добавление нового блюда
        if (!empty($_POST['new_dish'])) {
            $maxId = max(array_column($updatedMenu, 'id'));
            $newId = $maxId + 1;

            $newDish = $_POST['new_dish'];

            $name = sanitize($newDish['name']);
            $price = filter_var($newDish['price'], FILTER_VALIDATE_FLOAT);
            $category = sanitize($newDish['category']);
            $description = sanitize($newDish['description']);
            $popular = isset($newDish['popular']) ? true : false;
            $vegetarian = isset($newDish['vegetarian']) ? true : false;

            if ($name && $price !== false) {
                $updatedMenu[] = [
                    'id' => $newId,
                    'name' => $name,
                    'price' => (float)$price,
                    'category' => $category ?: 'Горячее',
                    'description' => $description ?: 'Описание блюда',
                    'popular' => $popular,
                    'vegetarian' => $vegetarian
                ];
            }
        }

        if (!empty($updatedMenu)) {
            file_put_contents($menuFile, json_encode($updatedMenu, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $menuItems = $updatedMenu;
            
            // Экспорт в Excel
            exportMenuToExcel($updatedMenu, $excelFile);
            
            // Обновление списка категорий
            $categories = array_unique(array_column($menuItems, 'category'));
            
            // Отправка уведомления в Telegram
            $botToken = "7803636920:AAGQbMQG-60axxxqx9P5hf5yXaalHRnaig4";
            $adminIds = [5232084143, 2142350115];
            $message = urlencode("✅ Меню обновлено! Добавлены новые блюда!");

            foreach ($adminIds as $chatId) {
                file_get_contents("https://api.telegram.org/bot{$botToken}/sendMessage?chat_id={$chatId}&text={$message}");
            }

            $saveSuccess = "Изменения сохранены!";
        }
    }
}

// Функция экспорта меню в Excel
function exportMenuToExcel($menuItems, $filename) {
    require_once 'PHPExcel.php'; // Подключаем библиотеку PHPExcel
    
    $objPHPExcel = new PHPExcel();
    $objPHPExcel->setActiveSheetIndex(0);
    $sheet = $objPHPExcel->getActiveSheet();
    
    // Заголовки столбцов
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'Название');
    $sheet->setCellValue('C1', 'Цена');
    $sheet->setCellValue('D1', 'Категория');
    $sheet->setCellValue('E1', 'Описание');
    $sheet->setCellValue('F1', 'Популярное');
    $sheet->setCellValue('G1', 'Вегетарианское');
    
    // Данные
    $row = 2;
    foreach ($menuItems as $item) {
        $sheet->setCellValue('A'.$row, $item['id']);
        $sheet->setCellValue('B'.$row, $item['name']);
        $sheet->setCellValue('C'.$row, $item['price']);
        $sheet->setCellValue('D'.$row, $item['category']);
        $sheet->setCellValue('E'.$row, $item['description']);
        $sheet->setCellValue('F'.$row, $item['popular'] ? 'Да' : 'Нет');
        $sheet->setCellValue('G'.$row, $item['vegetarian'] ? 'Да' : 'Нет');
        $row++;
    }
    
    // Сохранение файла
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save($filename);
}

// Обработка действий с умным калькулятором (корзиной)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Очистка калькулятора
    if (isset($_POST['clear_calculator'])) {
        file_put_contents($calculatorFile, json_encode([]));
        $calculatorItems = [];
        header("Location: {$_SERVER['PHP_SELF']}#calculator");
        exit;
    }
}

// Выход
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: {$_SERVER['PHP_SELF']}");
    exit;
}

// Получение уникальных категорий
$categories = array_unique(array_column($menuItems, 'category'));
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Буфет Колледжа - Вкусно и доступно</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Упрощенный HTML код (аналогичный предыдущей версии) -->
    <!-- Основные изменения: -->
    <!-- 1. Переименован "Калькулятор" в "Умный калькулятор" -->
    <!-- 2. Улучшена форма регистрации -->
    <!-- 3. Добавлена кнопка очистки калькулятора -->
    
    <script>
    // Обработка очистки умного калькулятора
    document.getElementById('clear-calculator-btn')?.addEventListener('click', function() {
        if (confirm('Вы уверены, что хотите очистить умный калькулятор?')) {
            document.getElementById('clear-calculator-form').submit();
        }
    });
    </script>
</body>
</html>