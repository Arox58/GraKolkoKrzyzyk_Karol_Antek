<?php
// Polaczenie z gotowa baza danych. Ten plik NIE tworzy bazy ani tabeli.
// Instalacje wykonuje sie tylko raz przez instalacja.php.

$host = '127.0.0.1';
$baza = 'kolko_i_krzyzyk_nowa';
$uzytkownik = 'root';
$haslo = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$baza;charset=utf8mb4",
        $uzytkownik,
        $haslo,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5
        ]
    );
} catch (PDOException $e) {
    error_log('Blad polaczenia z baza: ' . $e->getMessage());
    throw new RuntimeException(
        'Nie mozna polaczyc sie z baza kolko_i_krzyzyk_nowa. Uruchom MySQL w XAMPP, a nastepnie otworz plik instalacja.php. Szczegoly: ' . $e->getMessage(),
        0,
        $e
    );
}
?>
