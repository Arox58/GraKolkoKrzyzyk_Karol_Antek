console.log("skrypt działa");

let aktualnyStan = null;
let pobieranie = false;

const pola = document.querySelectorAll('.pole');
const komunikat = document.getElementById('komunikat');
const przyciskGotowosci = document.getElementById('przyciskGotowosci');
const informacjaGotowosci = document.getElementById('informacjaGotowosci');

// Pobiera odpowiedz JSON. Po 8 sekundach przerywa zawieszone zapytanie.
async function pobierzJson(adres, opcje = {}) {
    const kontroler = new AbortController();
    const limitCzasu = setTimeout(function() {
        kontroler.abort();
    }, 8000);

    try {
        const odpowiedz = await fetch(adres, {
            ...opcje,
            signal: kontroler.signal,
            cache: 'no-store'
        });

        const tekst = await odpowiedz.text();
        let dane;

        try {
            dane = JSON.parse(tekst);
        } catch (blad) {
            throw new Error('Serwer zwrócił nieprawidłową odpowiedź. Sprawdź PHP i bazę danych.');
        }

        if (!odpowiedz.ok && !dane.blad) {
            throw new Error('Błąd serwera: HTTP ' + odpowiedz.status);
        }

        return dane;
    } catch (blad) {
        if (blad.name === 'AbortError') {
            throw new Error('Serwer nie odpowiada od 8 sekund. Sprawdź, czy Apache i MySQL są uruchomione.');
        }
        throw blad;
    } finally {
        clearTimeout(limitCzasu);
    }
}

// Pobiera aktualny stan pokoju z PHP.
async function pobierzStan() {
    if (pobieranie) {
        return;
    }

    pobieranie = true;

    try {
        const dane = await pobierzJson('api.php?akcja=stan');

        if (!dane.success) {
            komunikat.textContent = dane.blad || 'Nie udało się pobrać gry.';
            komunikat.className = 'komunikat blad';
            return;
        }

        aktualnyStan = dane;
        pokazStan();
    } catch (blad) {
        komunikat.textContent = 'Problem z połączeniem: ' + blad.message;
        komunikat.className = 'komunikat blad';
    } finally {
        pobieranie = false;
    }
}

// Wyswietla dane otrzymane z serwera.
function pokazStan() {
    if (aktualnyStan === null) {
        return;
    }

    document.getElementById('kodPokoju').textContent = aktualnyStan.kod;
    document.getElementById('gracz1').textContent = aktualnyStan.gracz1;
    document.getElementById('gracz2').textContent = aktualnyStan.gracz2 || 'Oczekiwanie...';
    document.getElementById('symbol1').textContent = aktualnyStan.symbol1;
    document.getElementById('symbol2').textContent = aktualnyStan.symbol2;
    document.getElementById('wynik1').textContent = aktualnyStan.wynik1;
    document.getElementById('wynik2').textContent = aktualnyStan.wynik2;
    document.getElementById('runda').textContent = aktualnyStan.runda;

    komunikat.textContent = aktualnyStan.komunikat;
    komunikat.className = 'komunikat status-info';

    const informacjaKolej = document.getElementById('informacjaKolej');
    if (aktualnyStan.status === 'playing' && aktualnyStan.gracz2) {
        informacjaKolej.textContent = aktualnyStan.moja_tura ? 'Twoja kolej' : 'Ruch przeciwnika';
    } else if (aktualnyStan.status === 'waiting') {
        informacjaKolej.textContent = 'Oczekiwanie na gracza';
    } else {
        informacjaKolej.textContent = 'Runda zakończona';
    }

    // Ustawia symbole na planszy i blokuje niedozwolone pola.
    pola.forEach(function(pole, indeks) {
        const symbol = aktualnyStan.plansza[indeks];
        pole.textContent = symbol === '-' ? '' : symbol;
        pole.className = 'pole';

        if (symbol === 'X') {
            pole.classList.add('pole-x');
        } else if (symbol === 'O') {
            pole.classList.add('pole-o');
        }

        if (aktualnyStan.linia.includes(indeks)) {
            pole.classList.add('pole-wygrane');
        }

        pole.disabled = !(aktualnyStan.status === 'playing' && aktualnyStan.moja_tura && symbol === '-');
    });

    // Pokazuje przycisk kolejnej rundy lub nowego meczu.
    if (aktualnyStan.status === 'round_end' || aktualnyStan.status === 'match_end') {
        przyciskGotowosci.hidden = false;
        przyciskGotowosci.textContent = aktualnyStan.status === 'match_end' ? 'Zagraj ponownie' : 'Następna runda';

        const mojaGotowosc = (aktualnyStan.moj_numer === 1) ? aktualnyStan.gotowy1 : aktualnyStan.gotowy2;
        const gotowyPrzeciwnik = (aktualnyStan.moj_numer === 1) ? aktualnyStan.gotowy2 : aktualnyStan.gotowy1;

        if (mojaGotowosc === 1) {
            informacjaGotowosci.textContent = 'Potwierdziłeś gotowość. Czekasz na drugiego gracza.';
            przyciskGotowosci.disabled = true;
        } else {
            informacjaGotowosci.textContent = gotowyPrzeciwnik === 1 ? 'Przeciwnik jest gotowy.' : 'Obaj gracze muszą potwierdzić.';
            przyciskGotowosci.disabled = false;
        }
    } else {
        przyciskGotowosci.hidden = true;
        przyciskGotowosci.disabled = false;
        informacjaGotowosci.textContent = aktualnyStan.status === 'waiting'
            ? 'Udostępnij kod pokoju drugiemu graczowi.'
            : 'Wybierz puste pole, aby wykonać ruch.';
    }
}

// Wysyla ruch do PHP. Serwer jeszcze raz sprawdza, czy ruch jest dozwolony.
async function wykonajRuch(indeks) {
    if (aktualnyStan === null) {
        return;
    }

    try {
        const dane = await pobierzJson('api.php?akcja=ruch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pole: indeks })
        });

        if (!dane.success) {
            komunikat.textContent = dane.blad;
            komunikat.className = 'komunikat blad';
        }

        await pobierzStan();
    } catch (blad) {
        komunikat.textContent = 'Nie udało się wysłać ruchu: ' + blad.message;
        komunikat.className = 'komunikat blad';
    }
}

// Kazde pole ma swoj numer od 0 do 8.
pola.forEach(function(pole) {
    pole.addEventListener('click', function() {
        const numerPola = Number(pole.dataset.pole);
        wykonajRuch(numerPola);
    });
});

// Gracze potwierdzaja rozpoczecie nastepnej rundy.
przyciskGotowosci.addEventListener('click', async function() {
    przyciskGotowosci.disabled = true;

    try {
        const dane = await pobierzJson('api.php?akcja=gotowy', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });

        if (!dane.success) {
            komunikat.textContent = dane.blad;
            komunikat.className = 'komunikat blad';
        }

        await pobierzStan();
    } catch (blad) {
        komunikat.textContent = 'Nie udało się wysłać potwierdzenia: ' + blad.message;
        komunikat.className = 'komunikat blad';
        przyciskGotowosci.disabled = false;
    }
});

// Kopiuje kod pokoju, aby latwo wyslac go drugiej osobie.
document.getElementById('kopiujKod').addEventListener('click', async function() {
    if (aktualnyStan === null) {
        return;
    }

    try {
        await navigator.clipboard.writeText(aktualnyStan.kod);
        this.textContent = 'Skopiowano!';
    } catch (blad) {
        this.textContent = 'Kod: ' + aktualnyStan.kod;
    }
});

// Pierwsze pobranie oraz automatyczna synchronizacja co sekunde.
pobierzStan();
setInterval(pobierzStan, 1000);
