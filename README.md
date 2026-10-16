Izvēlētā ideja: 3. Personīgais budžeta plānotājs ar kategorijām · Lietotās valodas: PHP, MySQL, HTML, JavaScript

## Struktūra un palaišana
| Fails | Slānis |
|---|---|
| `index.php` | lietotāja saskarne (lapas, formas, diagramma) |
| `logic.php` | programmas loģika (validācija, autentifikācija, budžeta aprēķini) |
| `data.php` | datu apstrāde/saglabāšana (PDO + SQL vaicājumi) |
| `schema.sql` | datubāzes shēma |

1. Ievieto mapi `htdocs/budget` (XAMPP) un palaid Apache + MySQL.
2. phpMyAdmin → **Import** → `schema.sql`. Ja vajag, pārlabo piekļuves datus `data.php` sākumā.
3. Atver `http://localhost/budget/index.php`, reģistrējies un piesakies.

## Izmantotie resursi no interneta
| Resurss | Kur lietots | Kāpēc / ko pielāgoju |
|---|---|---|
| [Pico.css 2.0.6](https://picocss.com/) | `index.php` (CDN) | Gatavs minimāls stils bez atsevišķa CSS faila; tikai pievienotas klases (`grid`, `container`). |
| [Chart.js 4.4.1](https://www.chartjs.org/) · [Doughnut piemērs](https://www.chartjs.org/docs/latest/charts/doughnut.html) | `index.php` apakšā | Izdevumu diagramma; piemērs pielāgots, lai dati nāk no PHP (`json_encode`). |
| [PHP manual: PDO connections](https://www.php.net/manual/en/pdo.connections.php) | `data.php`, `db()` | Savienojuma shēma; iestatītas opcijas (izņēmumi, `FETCH_ASSOC`, bez emulācijas), lai sagatavotie vaicājumi pasargātu no SQL injekcijas. |
| [PHP manual: password_hash](https://www.php.net/manual/en/function.password-hash.php) | `logic.php`, `register()`/`login()` | Drošai paroļu glabāšanai (`password_hash` + `password_verify`), nevis paroles tekstā. |
| [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html) | `logic.php`, `csrf_field()`/`csrf_check()` | Sinhronizētā marķiera shēma; realizēta ar `random_bytes` un `hash_equals`. |
| [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html) | `logic.php`, `login()` | Vienāds kļūdas ziņojums un `session_regenerate_id` pēc pieteikšanās. |

## Validācijas noteikumi (`logic.php`, funkcijas `v_*`)
| Lauks | Noteikums | Pamatojums |
|---|---|---|
| E-pasts | `FILTER_VALIDATE_EMAIL`, ≤ 254 rakstz., mazie burti | RFC 5322 (formāts), RFC 5321 (garums) |
| Parole | 8–128 rakstzīmes, glabāta kā bcrypt hash | NIST SP 800-63B; VDAR 32. pants (drošība) |
| Summa | pozitīva, līdz 8 cipariem + 2 decimālzīmes | atbilst `DECIMAL(10,2)` |
| Datums | `GGGG-MM-DD`, esošs datums | ISO 8601 |
| Kategorija | nosaukums 2–50 rakstz.; veids tikai `income`/`expense`; ierakstā jābūt paša lietotāja kategorijai | datu integritāte, piekļuves kontrole (OWASP ASVS V4) |
| Izvade | `htmlspecialchars` (`h()`) | XSS novēršana |
| Datu minimizācija | glabāts tikai e-pasts un paroles hash | VDAR (ES 2016/679) 5. panta 1.c) punkts |

Validācija notiek gan pārlūkā (HTML5 atribūti `required`, `pattern`, `maxlength`), gan serverī (`v_*`).
