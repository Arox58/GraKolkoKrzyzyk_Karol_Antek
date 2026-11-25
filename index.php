<?php
session_start();

$komunikat = $_SESSION['komunikat'] ?? '';
unset($_SESSION['komunikat']);

$jestGra = !empty($_SESSION['kod_pokoju']) && !empty($_SESSION['token_gracza']);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kółko i krzyżyk online</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="strona-menu">
    <main class="menu">
        <div class="logo">X <span>O</span></div>
        <p class="nadtytul">GRA DLA DWÓCH OSÓB</p>
        <h1>Kółko i krzyżyk online</h1>
        <p class="opis">Zaproś znajomego i sprawdźcie, kto pierwszy zdobędzie trzy zwycięstwa.</p>

        <?php if ($komunikat !== ''): ?>
            <div class="komunikat blad"><?php echo htmlspecialchars($komunikat); ?></div>
        <?php endif; ?>

        <?php if ($jestGra): ?>
            <a class="przycisk przycisk-glowny" href="gra.php">Wróć do swojej gry</a>
        <?php endif; ?>

        <section class="karta-menu">
            <h2>Utwórz rozgrywkę</h2>
            <form action="akcje.php" method="POST">
                <input type="hidden" name="akcja" value="utworz">
                <label for="nick-utworz">Twój pseudonim</label>
                <input type="text" id="nick-utworz" name="nick" maxlength="20" placeholder="Np. Ola" required>
                <button class="przycisk przycisk-glowny" type="submit">Utwórz pokój</button>
            </form>

            <div class="separator"><span>lub</span></div>

            <h2>Dołącz do rozgrywki</h2>
            <form action="akcje.php" method="POST">
                <input type="hidden" name="akcja" value="dolacz">
                <label for="nick-dolacz">Twój pseudonim</label>
                <input type="text" id="nick-dolacz" name="nick" maxlength="20" placeholder="Np. Kuba" required>
                <label for="kod">Kod pokoju</label>
                <input type="text" id="kod" name="kod" maxlength="6" placeholder="Np. AB7K2Q" required>
                <button class="przycisk przycisk-obrys" type="submit">Dołącz do gry</button>
            </form>
        </section>

        <p class="stopka-menu">Gra kończy się po zdobyciu 3 zwycięstw.</p>
        <p class="stopka-menu"><a href="instalacja.php">Pierwsza instalacja / naprawa bazy danych</a></p>
    </main>
</body>
</html>
