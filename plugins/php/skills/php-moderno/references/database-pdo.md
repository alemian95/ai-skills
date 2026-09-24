# Database con PDO

Fonti: [PHP The Right Way — Databases](https://phptherightway.com/#databases), in particolare [PDO Extension](https://phptherightway.com/#pdo_extension) e [Abstraction Layers](https://phptherightway.com/#databases_abstraction_layers).

Vale per codice senza ORM o per query dirette. Con un framework usa il suo strato dati e passa a SQL grezzo solo con binding: Laravel query builder/Eloquent (`DB::select('... where id = ?', [$id])`), Symfony Doctrine ORM/DBAL, Laminas `laminas-db` o Doctrine. In progetti senza framework che crescono, valuta Doctrine DBAL o un query builder prima di scrivere SQL concatenato a mano. Le regole su parametri, identificatori, transazioni e `utf8mb4` valgono per tutti.

## Indice
1. Connessione
2. Query
3. Transazioni
4. Query dinamiche
5. Organizzazione del codice
6. UTF-8 e MySQL

## 1. Connessione

```php
$pdo = PDO::connect(                                   // 8.4: restituisce Pdo\Mysql, Pdo\Pgsql, Pdo\Sqlite…
    'mysql:host=db;port=3306;dbname=app;charset=utf8mb4',
    $settings->dbUser,
    $settings->dbPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,   // predefinito dalla 8.0, esplicitalo comunque
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,           // prepared statement reali, tipi nativi in lettura
    ],
);
```

- Con PHP < 8.4 usa `new PDO(...)` con le stesse opzioni.
- Dalla 8.4 le costanti specifiche del driver stanno nelle sottoclassi (`Pdo\Mysql::ATTR_USE_BUFFERED_QUERY` invece di `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY`); le vecchie funzionano ancora ma sono in dismissione.
- `charset=utf8mb4` nel DSN: senza, la connessione può usare un charset diverso da quello delle tabelle.
- Crea la connessione una volta per richiesta, nel container, e iniettala. Le credenziali arrivano dalla configurazione, mai da `$_ENV` letto dentro le classi.
- Connessioni persistenti (`ATTR_PERSISTENT`) solo con motivazione misurata: condividono stato (transazioni aperte, variabili di sessione) tra richieste.

## 2. Query

```php
$stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = :id AND active = :active');
$stmt->execute(['id' => $id, 'active' => 1]);
$user = $stmt->fetch();                 // array|false

$names = $pdo->prepare('SELECT name FROM users WHERE team_id = ?');
$names->execute([$teamId]);
$list = $names->fetchAll(PDO::FETCH_COLUMN);        // list<string>

$map = $pdo->query('SELECT id, name FROM teams')->fetchAll(PDO::FETCH_KEY_PAIR); // [id => name]
```

- Ogni valore esterno passa come parametro legato. `query()` solo per SQL completamente statico.
- `fetch()` restituisce `false` quando non ci sono righe: gestiscilo esplicitamente.
- Con prepared emulati disattivati, `LIMIT :n` funziona con `execute(['n' => $n])` su MySQL; con emulazione attiva serve `bindValue(':n', $n, PDO::PARAM_INT)`.
- Idrata oggetti esplicitamente (`array_map(User::fromRow(...), $rows)`) invece di `FETCH_CLASS`, che scrive le proprietà senza passare dal costruttore e ignora `readonly` e validazione.
- Grandi volumi: itera sullo statement (`foreach ($stmt as $row)`) invece di `fetchAll()`; su MySQL disattiva il buffering (`Pdo\Mysql::ATTR_USE_BUFFERED_QUERY => false`) per non caricare tutto in memoria.

## 3. Transazioni

```php
$pdo->beginTransaction();
try {
    $debit->execute([...]);
    $credit->execute([...]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

- Incapsula il pattern in un metodo (`$db->transactional(fn() => ...)`) invece di ripeterlo.
- In MySQL le istruzioni DDL (`CREATE`, `ALTER`) eseguono un commit implicito: non metterle in transazione aspettandoti un rollback.
- Tieni le transazioni brevi: niente chiamate HTTP o I/O lento al loro interno.

## 4. Query dinamiche

**Clausola `IN`**
```php
$placeholders = implode(', ', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM users WHERE id IN ($placeholders)");
$stmt->execute(array_values($ids));
```
Gestisci a parte il caso `$ids === []` (SQL non valido).

**Filtri opzionali**: costruisci insieme frammenti SQL statici e array di parametri; nessun valore finisce nella stringa.
```php
$where = ['1 = 1'];
$params = [];
if ($filter->email !== null) {
    $where[] = 'email = :email';
    $params['email'] = $filter->email;
}
$sql = 'SELECT * FROM users WHERE ' . implode(' AND ', $where);
```

**Identificatori** (colonne, tabelle, `ORDER BY`): solo da whitelist (esempio in `sicurezza.md`).

**`LIKE`**: `$params['q'] = '%' . addcslashes($term, '%_\\') . '%';` se l'utente non deve poter usare i caratteri jolly.

## 5. Organizzazione del codice

- SQL confinato in classi dedicate (repository o query object) che ricevono `PDO` nel costruttore e restituiscono tipi del dominio, non `PDOStatement` né array grezzi.
- Il dominio dipende da un'interfaccia (`UserRepository`) solo se esiste un motivo concreto (più implementazioni, test senza database); altrimenti la classe concreta basta.
- Mai SQL nei template né nei controller/handler.
- Test di integrazione dei repository su un database reale (SQLite in memoria solo se il dialetto SQL è compatibile, altrimenti container con MySQL/PostgreSQL).
- Migrazioni versionate (Doctrine Migrations, Phinx) invece di script SQL manuali.

## 6. UTF-8 e MySQL

- Database, tabelle e colonne in `utf8mb4` (MySQL `utf8`/`utf8mb3` non contiene emoji e parte dei caratteri Unicode). Collation `utf8mb4_0900_ai_ci` su MySQL 8, `utf8mb4_unicode_ci` su versioni precedenti e MariaDB.
- Charset nel DSN (sezione 1): è la connessione a determinare come vengono trasmessi i dati.
