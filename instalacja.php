<?php
// Ten plik uruchom tylko przy pierwszej instalacji lub gdy baza nie istnieje.
$host = '127.0.0.1';
$baza = 'kolko_i_krzyzyk_nowa';
$uzytkownik = 'root';
$haslo = '';
$komunikat = '';
$blad = false;

try {
    $pdo = new PDO(
        "mysql:host=$host;charset=utf8mb4",
        $uzytkownik,
        $haslo,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]
    );

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$baza` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$baza`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pokoje (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kod CHAR(6) NOT NULL UNIQUE,
        gracz1 VARCHAR(20) NOT NULL,
        gracz2 VARCHAR(20) DEFAULT NULL,
        token1 CHAR(64) NOT NULL,
        token2 CHAR(64) DEFAULT NULL,
        plansza CHAR(9) NOT NULL DEFAULT '---------',
        wynik1 INT NOT NULL DEFAULT 0,
        wynik2 INT NOT NULL DEFAULT 0,
        kolej TINYINT NOT NULL DEFAULT 1,
        runda INT NOT NULL DEFAULT 1,
        status VARCHAR(20) NOT NULL DEFAULT 'waiting',
        zwyciezca TINYINT NOT NULL DEFAULT 0,
        linia VARCHAR(20) NOT NULL DEFAULT '',
        gotowy1 TINYINT NOT NULL DEFAULT 0,
        gotowy2 TINYINT NOT NULL DEFAULT 0,
        utworzono TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $komunikat = 'Baza danych i tabela pokoje są gotowe. Możesz rozpocząć grę.';
} catch (PDOException $e) {
    $blad = true;
    $komunikat = 'Nie udało się przygotować bazy danych. Sprawdź, czy MySQL jest uruchomiony oraz czy dane logowania w instalacja.php są poprawne. Szczegóły: ' . $e->getMessage();
    error_log('Blad instalacji bazy: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalacja gry</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="strona-menu">
    <main class="menu">
        <div class="logo">X <span>O</span></div>
        <h1>Instalacja bazy danych</h1>
        <div class="komunikat <?php echo $blad ? 'blad' : 'status-info'; ?>">
            <?php echo htmlspecialchars($komunikat, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php if (!$blad): ?>
            <a class="przycisk przycisk-glowny" href="index.php">Przejdź do gry</a>
        <?php else: ?>
            <a class="przycisk przycisk-obrys" href="instalacja.php">Spróbuj ponownie</a>
        <?php endif; ?>
    </main>
</body>
</html>
