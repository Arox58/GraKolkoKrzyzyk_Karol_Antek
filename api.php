<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Funkcja wysyla odpowiedz do JavaScript i konczy dzialanie pliku.
function odpowiedz($dane, $kod = 200)
{
    http_response_code($kod);
    echo json_encode($dane, JSON_UNESCAPED_UNICODE);
    exit;
}

// Bledy polaczenia zwracamy jako JSON, zeby JavaScript mogl je pokazac.
try {
    require_once 'db.php';
} catch (Throwable $e) {
    error_log('Blad API - baza danych: ' . $e->getMessage());
    odpowiedz(['success' => false, 'blad' => $e->getMessage()], 500);
}

if (empty($_SESSION['kod_pokoju']) || empty($_SESSION['token_gracza'])) {
    odpowiedz(['success' => false, 'blad' => 'Nie znaleziono Twojej sesji gry. Wróć do menu.'], 401);
}

$kod = $_SESSION['kod_pokoju'];
$token = $_SESSION['token_gracza'];
$akcja = $_GET['akcja'] ?? 'stan';
// Nie blokujemy innych zapytan z tej samej sesji podczas pracy z baza.
session_write_close();

// Sprawdza, czy token nalezy do pierwszego czy drugiego gracza.
function numerGracza($pokoj, $token)
{
    if (hash_equals($pokoj['token1'], $token)) {
        return 1;
    }

    if (!empty($pokoj['token2']) && hash_equals($pokoj['token2'], $token)) {
        return 2;
    }

    return 0;
}

// Pobiera pokoj i sprawdza, czy gracz nadal do niego nalezy.
function pobierzPokoj($pdo, $kod, $token)
{
    $zapytanie = $pdo->prepare('SELECT * FROM pokoje WHERE kod = ?');
    $zapytanie->execute([$kod]);
    $pokoj = $zapytanie->fetch();

    if (!$pokoj || numerGracza($pokoj, $token) === 0) {
        odpowiedz(['success' => false, 'blad' => 'Nie masz dostępu do tego pokoju.'], 403);
    }

    return $pokoj;
}

if ($akcja === 'stan') {
    try {
        $pokoj = pobierzPokoj($pdo, $kod, $token);
    } catch (Throwable $e) {
        error_log('Blad pobierania stanu gry: ' . $e->getMessage());
        odpowiedz(['success' => false, 'blad' => 'Nie udało się pobrać stanu gry. ' . $e->getMessage()], 500);
    }
    $mojNumer = numerGracza($pokoj, $token);
    $symbol1 = ((int)$pokoj['runda'] % 2 === 1) ? 'X' : 'O';
    $symbol2 = ($symbol1 === 'X') ? 'O' : 'X';
    $zwyciezca = (int)$pokoj['zwyciezca'];

    $nazwaZwyciezcy = '';
    if ($zwyciezca === 1) {
        $nazwaZwyciezcy = $pokoj['gracz1'];
    } elseif ($zwyciezca === 2) {
        $nazwaZwyciezcy = $pokoj['gracz2'];
    }

    $komunikat = '';
    if ($pokoj['status'] === 'waiting') {
        $komunikat = 'Oczekiwanie na drugiego gracza. Udostępnij mu kod pokoju.';
    } elseif ($pokoj['status'] === 'playing') {
        if ((int)$pokoj['kolej'] === $mojNumer) {
            $komunikat = 'Twoja kolej!';
        } else {
            $nazwaKolejnego = ((int)$pokoj['kolej'] === 1) ? $pokoj['gracz1'] : $pokoj['gracz2'];
            $komunikat = 'Teraz ruch: ' . $nazwaKolejnego;
        }
    } elseif ($pokoj['status'] === 'round_end') {
        $komunikat = ($zwyciezca === 0) ? 'Remis! Runda zakończona.' : 'Rundę wygrywa ' . $nazwaZwyciezcy . '!';
    } elseif ($pokoj['status'] === 'match_end') {
        $komunikat = 'Mecz zakończony! Zwycięża ' . $nazwaZwyciezcy . ' wynikiem ' . $pokoj['wynik1'] . ':' . $pokoj['wynik2'] . '.';
    }

    $wygranaLinia = [];
    if ($pokoj['linia'] !== '') {
        $wygranaLinia = array_map('intval', explode(',', $pokoj['linia']));
    }

    odpowiedz([
        'success' => true,
        'kod' => $pokoj['kod'],
        'status' => $pokoj['status'],
        'gracz1' => $pokoj['gracz1'],
        'gracz2' => $pokoj['gracz2'],
        'symbol1' => $symbol1,
        'symbol2' => $symbol2,
        'plansza' => str_split($pokoj['plansza']),
        'wynik1' => (int)$pokoj['wynik1'],
        'wynik2' => (int)$pokoj['wynik2'],
        'runda' => (int)$pokoj['runda'],
        'kolej' => (int)$pokoj['kolej'],
        'moja_tura' => ($pokoj['status'] === 'playing' && (int)$pokoj['kolej'] === $mojNumer),
        'moj_numer' => $mojNumer,
        'zwyciezca' => $zwyciezca,
        'nazwa_zwyciezcy' => $nazwaZwyciezcy,
        'linia' => $wygranaLinia,
        'gotowy1' => (int)$pokoj['gotowy1'],
        'gotowy2' => (int)$pokoj['gotowy2'],
        'komunikat' => $komunikat
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    odpowiedz(['success' => false, 'blad' => 'Nieprawidłowe żądanie.'], 405);
}

$dane = json_decode(file_get_contents('php://input'), true);
if (!is_array($dane)) {
    $dane = [];
}

if ($akcja === 'ruch') {
    $pole = isset($dane['pole']) ? (int)$dane['pole'] : -1;

    if ($pole < 0 || $pole > 8) {
        odpowiedz(['success' => false, 'blad' => 'Nieprawidłowe pole.'], 400);
    }

    try {
        $pdo->beginTransaction();

        $zapytanie = $pdo->prepare('SELECT * FROM pokoje WHERE kod = ? FOR UPDATE');
        $zapytanie->execute([$kod]);
        $pokoj = $zapytanie->fetch();

        if (!$pokoj || numerGracza($pokoj, $token) === 0) {
            $pdo->rollBack();
            odpowiedz(['success' => false, 'blad' => 'Nie masz dostępu do tego pokoju.'], 403);
        }

        $mojNumer = numerGracza($pokoj, $token);

        if ($pokoj['status'] !== 'playing') {
            $pdo->rollBack();
            odpowiedz(['success' => false, 'blad' => 'Ta runda jest już zakończona.']);
        }

        if ((int)$pokoj['kolej'] !== $mojNumer) {
            $pdo->rollBack();
            odpowiedz(['success' => false, 'blad' => 'Teraz jest kolej drugiego gracza.']);
        }

        $plansza = str_split($pokoj['plansza']);
        if ($plansza[$pole] !== '-') {
            $pdo->rollBack();
            odpowiedz(['success' => false, 'blad' => 'To pole jest już zajęte.']);
        }

        $symbol1 = ((int)$pokoj['runda'] % 2 === 1) ? 'X' : 'O';
        $symbol = ($mojNumer === 1) ? $symbol1 : (($symbol1 === 'X') ? 'O' : 'X');
        $plansza[$pole] = $symbol;

        // Wszystkie mozliwe uklady trzech symboli.
        $mozliweLinie = [
            [0, 1, 2], [3, 4, 5], [6, 7, 8],
            [0, 3, 6], [1, 4, 7], [2, 5, 8],
            [0, 4, 8], [2, 4, 6]
        ];

        $zwyciezca = 0;
        $linia = '';

        foreach ($mozliweLinie as $uklad) {
            if (
                $plansza[$uklad[0]] !== '-' &&
                $plansza[$uklad[0]] === $plansza[$uklad[1]] &&
                $plansza[$uklad[1]] === $plansza[$uklad[2]]
            ) {
                $zwyciezca = $mojNumer;
                $linia = implode(',', $uklad);
                break;
            }
        }

        $wynik1 = (int)$pokoj['wynik1'];
        $wynik2 = (int)$pokoj['wynik2'];
        $status = 'playing';
        $kolej = ($mojNumer === 1) ? 2 : 1;

        if ($zwyciezca !== 0) {
            if ($zwyciezca === 1) {
                $wynik1++;
            } else {
                $wynik2++;
            }
            $status = 'round_end';

            if ($wynik1 >= 3 || $wynik2 >= 3) {
                $status = 'match_end';
            }
        } elseif (!in_array('-', $plansza, true)) {
            // Plansza jest pelna i nikt nie wygral - remis.
            $status = 'round_end';
        }

        $zapytanie = $pdo->prepare(
            'UPDATE pokoje SET plansza = ?, wynik1 = ?, wynik2 = ?, kolej = ?, status = ?, zwyciezca = ?, linia = ?, gotowy1 = 0, gotowy2 = 0 WHERE kod = ?'
        );
        $zapytanie->execute([
            implode('', $plansza), $wynik1, $wynik2, $kolej,
            $status, $zwyciezca, $linia, $kod
        ]);

        $pdo->commit();
        odpowiedz(['success' => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        odpowiedz(['success' => false, 'blad' => 'Wystąpił błąd podczas zapisywania ruchu.'], 500);
    }
}

if ($akcja === 'gotowy') {
    try {
        $pdo->beginTransaction();

        $zapytanie = $pdo->prepare('SELECT * FROM pokoje WHERE kod = ? FOR UPDATE');
        $zapytanie->execute([$kod]);
        $pokoj = $zapytanie->fetch();

        if (!$pokoj || numerGracza($pokoj, $token) === 0) {
            $pdo->rollBack();
            odpowiedz(['success' => false, 'blad' => 'Nie masz dostępu do tego pokoju.'], 403);
        }

        if ($pokoj['status'] !== 'round_end' && $pokoj['status'] !== 'match_end') {
            $pdo->rollBack();
            odpowiedz(['success' => false, 'blad' => 'Nie można teraz rozpocząć kolejnej rundy.']);
        }

        $mojNumer = numerGracza($pokoj, $token);
        $gotowy1 = (int)$pokoj['gotowy1'];
        $gotowy2 = (int)$pokoj['gotowy2'];

        if ($mojNumer === 1) {
            $gotowy1 = 1;
        } else {
            $gotowy2 = 1;
        }

        if ($gotowy1 === 1 && $gotowy2 === 1) {
            if ($pokoj['status'] === 'match_end') {
                // Obaj gracze potwierdzili nowy mecz. Zerujemy wynik.
                $nowaRunda = 1;
                $nowyWynik1 = 0;
                $nowyWynik2 = 0;
                $nowaKolej = 1;
            } else {
                $nowaRunda = (int)$pokoj['runda'] + 1;
                $nowyWynik1 = (int)$pokoj['wynik1'];
                $nowyWynik2 = (int)$pokoj['wynik2'];
                // W rundach nieparzystych X ma gracz 1, w parzystych X ma gracz 2.
                $nowaKolej = ($nowaRunda % 2 === 1) ? 1 : 2;
            }

            $zapytanie = $pdo->prepare(
                "UPDATE pokoje SET plansza = '---------', wynik1 = ?, wynik2 = ?, kolej = ?, runda = ?, status = 'playing', zwyciezca = 0, linia = '', gotowy1 = 0, gotowy2 = 0 WHERE kod = ?"
            );
            $zapytanie->execute([$nowyWynik1, $nowyWynik2, $nowaKolej, $nowaRunda, $kod]);
        } else {
            $zapytanie = $pdo->prepare('UPDATE pokoje SET gotowy1 = ?, gotowy2 = ? WHERE kod = ?');
            $zapytanie->execute([$gotowy1, $gotowy2, $kod]);
        }

        $pdo->commit();
        odpowiedz(['success' => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        odpowiedz(['success' => false, 'blad' => 'Nie udało się rozpocząć kolejnej rundy.'], 500);
    }
}

odpowiedz(['success' => false, 'blad' => 'Nieznana akcja.'], 400);
?>
