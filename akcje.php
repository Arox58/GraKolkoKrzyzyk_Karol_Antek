<?php
session_start();

// Przekierowuje do menu i pokazuje komunikat.
function pokazBlad($tekst)
{
    $_SESSION['komunikat'] = $tekst;
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pokazBlad('Nieprawidłowe żądanie.');
}

$akcja = $_POST['akcja'] ?? '';
$nick = trim($_POST['nick'] ?? '');

// W bazie pseudonim ma maksymalnie 20 znakow.
$liczbaZnakow = preg_match_all('/./us', $nick, $znaki);
if ($nick === '' || $liczbaZnakow === false || $liczbaZnakow > 20) {
    pokazBlad('Podaj pseudonim (maksymalnie 20 znaków).');
}

// Polaczenie wykonujemy dopiero po sprawdzeniu formularza.
try {
    require_once 'db.php';
} catch (Throwable $e) {
    pokazBlad($e->getMessage());
}

// Token rozpoznaje gracza, nawet po odswiezeniu strony.
$token = bin2hex(random_bytes(32));

if ($akcja === 'utworz') {
    $znalezionoKod = false;

    for ($i = 0; $i < 10; $i++) {
        $kod = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        try {
            $zapytanie = $pdo->prepare(
                "INSERT INTO pokoje (kod, gracz1, token1, plansza, wynik1, wynik2, kolej, runda, status)
                 VALUES (?, ?, ?, '---------', 0, 0, 1, 1, 'waiting')"
            );
            $zapytanie->execute([$kod, $nick, $token]);
            $znalezionoKod = true;
            break;
        } catch (PDOException $e) {
            $kodBledu = (int)($e->errorInfo[1] ?? 0);
            if ($kodBledu !== 1062) {
                error_log('Blad tworzenia pokoju: ' . $e->getMessage());
                pokazBlad('Nie udało się utworzyć pokoju. Szczegóły: ' . $e->getMessage());
            }
        }
    }

    if (!$znalezionoKod) {
        pokazBlad('Nie udało się wylosować kodu pokoju. Spróbuj ponownie.');
    }

    session_regenerate_id(true);
    $_SESSION['kod_pokoju'] = $kod;
    $_SESSION['token_gracza'] = $token;

    header('Location: gra.php');
    exit;
}

if ($akcja === 'dolacz') {
    $kod = strtoupper(trim($_POST['kod'] ?? ''));

    if (!preg_match('/^[A-Z0-9]{6}$/', $kod)) {
        pokazBlad('Kod pokoju musi mieć 6 znaków.');
    }

    try {
        $pdo->beginTransaction();

        $zapytanie = $pdo->prepare('SELECT * FROM pokoje WHERE kod = ? FOR UPDATE');
        $zapytanie->execute([$kod]);
        $pokoj = $zapytanie->fetch();

        if (!$pokoj) {
            $pdo->rollBack();
            pokazBlad('Nie znaleziono pokoju o podanym kodzie.');
        }

        if ($pokoj['gracz2'] !== null && $pokoj['gracz2'] !== '') {
            $pdo->rollBack();
            pokazBlad('Ten pokój jest już pełny.');
        }

        $zapytanie = $pdo->prepare(
            "UPDATE pokoje SET gracz2 = ?, token2 = ?, status = 'playing', kolej = 1 WHERE kod = ?"
        );
        $zapytanie->execute([$nick, $token, $kod]);
        $pdo->commit();

        session_regenerate_id(true);
        $_SESSION['kod_pokoju'] = $kod;
        $_SESSION['token_gracza'] = $token;

        header('Location: gra.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Blad dolaczania do pokoju: ' . $e->getMessage());
        pokazBlad('Nie udało się dołączyć do pokoju. Szczegóły: ' . $e->getMessage());
    }
}

pokazBlad('Nie wybrano prawidłowej akcji.');
?>
