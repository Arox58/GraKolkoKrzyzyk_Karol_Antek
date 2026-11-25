<?php
session_start();

if (empty($_SESSION['kod_pokoju']) || empty($_SESSION['token_gracza'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rozgrywka | Kółko i krzyżyk</title>
    <link rel="stylesheet" href="style.css">
    <script src="skrypt.js" defer></script>
</head>
<body class="strona-gry">
    <main class="gra">
        <header class="naglowek-gry">
            <a href="index.php" class="logo-maly">X <span>O</span></a>
            <div>
                <h1>Kółko i krzyżyk</h1>
                <p>Mecz do 3 zwycięstw</p>
            </div>
            <a href="index.php" class="link-menu">Menu</a>
        </header>

        <section class="panel-kodu">
            <div>
                <span class="etykieta">KOD POKOJU</span>
                <strong id="kodPokoju">------</strong>
            </div>
            <button type="button" class="przycisk-kopiuj" id="kopiujKod">Kopiuj kod</button>
        </section>

        <div id="komunikat" class="komunikat status-info">Łączenie z grą...</div>

        <section class="tablica-wyniku">
            <div class="gracz" id="panelGracza1">
                <span class="symbol symbol-x" id="symbol1">X</span>
                <div>
                    <strong id="gracz1">Gracz 1</strong>
                    <span class="opis-gracza">Gracz pierwszy</span>
                </div>
                <strong class="wynik" id="wynik1">0</strong>
            </div>
            <div class="separator-wyniku">:</div>
            <div class="gracz gracz-prawy" id="panelGracza2">
                <span class="symbol symbol-o" id="symbol2">O</span>
                <div>
                    <strong id="gracz2">Oczekiwanie...</strong>
                    <span class="opis-gracza">Gracz drugi</span>
                </div>
                <strong class="wynik" id="wynik2">0</strong>
            </div>
        </section>

        <section class="informacje-gry">
            <div>RUNDA <strong id="runda">1</strong></div>
            <div id="informacjaKolej">Oczekiwanie na gracza</div>
        </section>

        <section class="plansza" id="plansza">
            <button type="button" class="pole" data-pole="0" aria-label="Pole 1"></button>
            <button type="button" class="pole" data-pole="1" aria-label="Pole 2"></button>
            <button type="button" class="pole" data-pole="2" aria-label="Pole 3"></button>
            <button type="button" class="pole" data-pole="3" aria-label="Pole 4"></button>
            <button type="button" class="pole" data-pole="4" aria-label="Pole 5"></button>
            <button type="button" class="pole" data-pole="5" aria-label="Pole 6"></button>
            <button type="button" class="pole" data-pole="6" aria-label="Pole 7"></button>
            <button type="button" class="pole" data-pole="7" aria-label="Pole 8"></button>
            <button type="button" class="pole" data-pole="8" aria-label="Pole 9"></button>
        </section>

        <section class="panel-kolejnej-rundy" id="panelGotowosci">
            <p id="informacjaGotowosci">Po zakończeniu rundy tutaj pojawi się przycisk.</p>
            <button type="button" class="przycisk przycisk-glowny" id="przyciskGotowosci" hidden>Następna runda</button>
        </section>

        <p class="podpowiedz">Wybierz puste pole, aby wykonać ruch. Gra odświeża się automatycznie.</p>
    </main>
</body>
</html>
