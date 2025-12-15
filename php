<?php
// 1. НАЛАШТУВАННЯ ЗБЕРЕЖЕННЯ
$results_file = 'survey_results.csv';

// 2. Визначення питань опитування ТА ІНДИВІДУАЛЬНИХ ВАРІАНТІВ (НОВА СТРУКТУРА)
$survey_config = [
    
    // Питання 1
    1 => [
        'text' => "Чи слідкуєте за Євробаченям?",
        'options' => ['Так, щороку', 'Дивлюся лише фінал', 'Ні, не цікавлюся']
    ],
    
    // Питання 2
    2 => [
        'text' => "Який був ваш фаворит на Євробаченні?",
        'options' => ['Хорватія (Baby Lasagna)', 'Швейцарія (Nemo)', 'Україна (alyona alyona & Jerry Heil)', 'Інша країна'] 
    ],
    
    // Питання 3
    3 => [
        'text' => "Кого хотіли б побачити на Євробаченні 2026?",
        'options' => ['The Hardkiss', 'ONUKA', 'MONATIK', 'Іншого виконавця']
    ],
    
    // Питання 4
    4 => [
        'text' => "Чи сподобався виступ Ziferblat?",
        'options' => ['Так, це було дуже стильно', 'Ні, не мій формат', 'Я не дивився(-лася)']
    ],
    
    // Питання 5
    5 => [
        'text' => "Яке місце вони мали б отримати на вашу думку?",
        'options' => ['Топ 5', '6 - 10 місце', 'Нижче 10-го місця']
    ],
    
    // Питання 6
    6 => [
        'text' => "Чи вважаєте Євробачення не потрібним?",
        'options' => ['Ні, це важливий культурний обмін', 'Так, це політизоване шоу', 'Мені байдуже']
    ],
    
    // Питання 7
    7 => [
        'text' => "Як ви вважаєте чому Україна не потрапила в топ 3 на Євробаченні?",
        'options' => ['Слабкий номер', 'Політичні причини', 'Недостатня промоція', 'Інша причина']
    ]
];

// Створення масиву $questions для сумісності з логікою заголовків CSV
$questions = array_column($survey_config, 'text'); 

// --- ЛОГІКА ОБРОБКИ ФОРМ ---

$stage = 'registration'; // Початкова стадія
$message = '';
$user_data = [];
$answers = [];

// Перевірка, чи була надіслана форма (метод POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. ОБРОБКА РЕЄСТРАЦІЇ
    if (isset($_POST['action']) && $_POST['action'] == 'register') {
        if (!empty($_POST['name']) && !empty($_POST['email'])) {
            $user_data = [
                'name' => trim($_POST['name']),
                'email' => filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : 'Invalid Email',
                'timestamp' => date("Y-m-d H:i:s")
            ];
            $stage = 'servey.php';
        } else {
            $message = '<p class="error">Будь ласка, заповніть усі поля для реєстрації.</p>';
        }
    }
    
    // 2. ОБРОБКА ОПИТУВАННЯ (ОНОВЛЕНО ДЛЯ $survey_config)
    elseif (isset($_POST['action']) && $_POST['action'] == 'submit_survey') {
        // Отримання даних користувача, переданих через приховані поля
        $user_data = [
            'name' => trim($_POST['user_name']),
            'email' => trim($_POST['user_email']),
            'timestamp' => trim($_POST['user_timestamp'])
        ];
        
        $all_answered = true;
        $raw_answers = [];

        // Збір відповідей та перевірка на пропуски
        foreach ($survey_config as $id => $question_data) { // ВИКОРИСТОВУЄМО НОВИЙ МАСИВ
            $q_text = $question_data['text']; // Отримуємо текст питання
            
            $key = "q{$id}";
            
            // Використовуємо ?? (Null Coalescing Operator) для безпечного отримання даних
            $selected_option = $_POST[$key] ?? '';
            $custom_answer = trim($_POST["{$key}_custom"] ?? '');

            if ($selected_option === '' && $custom_answer === '') {
                $all_answered = false;
                break; // Перериваємо, якщо відповідь пропущена
            }

            // Форматування відповіді (зберігаємо за текстом питання для коректного CSV)
            if ($selected_option === 'custom') {
                $answers[$q_text] = "Своя відповідь: " . ($custom_answer ?: 'Нічого не введено');
            } else {
                $answers[$q_text] = $selected_option ?: 'Пропущено';
            }
        }

        if ($all_answered) {
            // ФОРМАТУВАННЯ ТА ЗБЕРЕЖЕННЯ ДАНИХ У CSV
            $data_array = array_merge(
                [$user_data['timestamp'], $user_data['name'], $user_data['email']],
                array_values($answers)
            );
            
            // Екранування значень для CSV
            $safe_data = array_map(function($item) {
                // Замінюємо подвійні лапки всередині на подвійні подвійні лапки
                return '"' . str_replace('"', '""', $item) . '"'; 
            }, $data_array);

            // Створення рядка CSV
            $csv_line = implode(",", $safe_data) . "\n";

            // Перевіряємо, чи існує файл для додавання заголовка
            if (!file_exists($results_file)) {
                $headers = ["Дата", "Ім'я", "Email"];
                // Використовуємо $questions для заголовків (для коректного порядку)
                foreach ($questions as $id => $text) { 
                    $headers[] = "Питання {$id}";
                }
                $header_line = implode(",", array_map(fn($h) => '"' . $h . '"', $headers)) . "\n";
                file_put_contents($results_file, $header_line, LOCK_EX);
            }

            // Запис даних
            if (file_put_contents($results_file, $csv_line, FILE_APPEND | LOCK_EX) !== false) {
                $stage = 'thank_you';
            } else {
                $stage = 'error';
                $message = '<p class="error">Помилка: Не вдалося записати дані у файл на сервері.</p>';
            }
            
        } else {
            $stage = 'survey'; // Повернення до опитування, якщо пропущено
            $message = '<p class="error">Будь ласка, дайте відповіді на всі 7 питань.</p>';
            // Для продовження опитування дані користувача передаємо знову
            $user_data = [
                'name' => $_POST['user_name'],
                'email' => $_POST['user_email'],
                'timestamp' => $_POST['user_timestamp']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Онлайн Опитування</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        input[type="text"], input[type="email"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        .btn:hover { background-color: #0056b3; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        
        /* Стилі для радіо-кнопок */
        .radio-label { 
            display: block; 
            padding: 10px; 
            margin-bottom: 8px; 
            border: 2px solid #ccc; 
            border-radius: 5px; 
            cursor: pointer; 
            transition: all 0.2s;
            position: relative;
        }
        .radio-label:hover { background-color: #e9ecef; }
        
        /* Приховання стандартної радіо-кнопки */
        .radio-label input[type="radio"] { 
            display: none; 
        }
        
        /* Підсвічування обраної відповіді */
        .radio-label input[type="radio"]:checked + span {
            background-color: #d4edda; /* Світло-зелений фон */
            border-color: #28a745; /* Зелена рамка */
            color: #155724; /* Темно-зелений текст */
        }
        
        /* Стиль для елемента span всередині label */
        .radio-label span {
            display: block;
            padding: 0;
            margin: 0;
            border-radius: 3px;
        }

        /* Стиль для власного текстового поля */
        .custom-input { 
            margin-top: 5px; 
            padding: 8px; 
            width: 98%; 
            display: none; /* За замовчуванням прихований */
        }
    </style>
</head>
<body>
<div class="container">
        <h1>Онлайн Опитування</h1>

        <?php echo $message; ?>

        <?php if ($stage === 'registration'): ?>
            <form action="servey.php" method="POST">
                <h2>1. Реєстрація</h2>
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label for="name">Ваше ім'я:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Ваш Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <button type="submit" class="btn">Почати Опитування</button>
            </form>

        <?php elseif ($stage === 'servey.php'): ?>
            <form action="servey.php" method="POST">
                <h2>2. Питання Опитування</h2>
                <input type="hidden" name="action" value="submit_survey">
                <input type="hidden" name="user_name" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>">
                <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                <input type="hidden" name="user_timestamp" value="<?php echo htmlspecialchars($user_data['timestamp'] ?? ''); ?>">

                <?php foreach ($survey_config as $id => $question_data): 
                    $q_text = $question_data['text'];
                ?>
                    <div class="form-group">
                        <label><strong><?php echo $id; ?>. <?php echo htmlspecialchars($q_text); ?></strong></label>
                        <?php 
                        $input_name = "q{$id}";
                        $options_to_use = $question_data['options']; // Беремо індивідуальні опції
                        
                        // Вивід індивідуальних відповідей
                        foreach ($options_to_use as $option_text): ?>
                            <label class="radio-label">
                                <input type="radio" name="<?php echo $input_name; ?>" value="<?php echo htmlspecialchars($option_text); ?>" onclick="hideCustomInput('<?php echo $input_name; ?>')">
                                <span><?php echo htmlspecialchars($option_text); ?></span>
                            </label>
                        <?php endforeach; ?>

                        <label class="radio-label">
                            <input type="radio" name="<?php echo $input_name; ?>" value="custom" onclick="showCustomInput('<?php echo $input_name; ?>')">
                            <span>Своя відповідь (введіть нижче):</span>
                        </label>
                        
                        <input type="text" 
                               id="custom_<?php echo $input_name; ?>" 
                               name="<?php echo $input_name; ?>_custom" 
                               class="custom-input" 
                               placeholder="Введіть ваш варіант">
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" class="btn">Зберегти відповіді та Завершити Опитування</button>
            </form>

        <?php elseif ($stage === 'thank_you'): ?>
            <div class="form-group success">
                <h2>✅ Опитування завершено!</h2>
                <p><strong>Дякуємо, що пройшли опитування!</strong> Ваші дані було успішно збережено на сервері.</p>
            </div>
        <?php elseif ($stage === 'error'): ?>
             <div class="form-group error">
                <h2>❌ Помилка</h2>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function showCustomInput(name) {
            // Показує текстове поле для власної відповіді
            document.getElementById('custom_' + name).style.display = 'block';
        }

        function hideCustomInput(name) {
            // Приховує текстове поле і очищає його значення
            const customInput = document.getElementById('custom_' + name);
            customInput.style.display = 'none';
            customInput.value = ''; // Очищаємо, щоб не відправити стару кастомну відповідь
        }

        // Ініціалізація: приховуємо всі кастомні поля при завантаженні
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                const name = radio.name;
                const customInputId = 'custom_' + name;
                
                // Перевірка, чи існує поле custom_...
                const customInput = document.getElementById(customInputId);

                // Якщо існує, приховуємо його
                if (customInput) {
                    customInput.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
