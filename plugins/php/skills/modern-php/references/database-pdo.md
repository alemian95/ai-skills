# Databases with PDO

Sources: [PHP The Right Way — Databases](https://phptherightway.com/#databases), in particular [PDO Extension](https://phptherightway.com/#pdo_extension) and [Abstraction Layers](https://phptherightway.com/#databases_abstraction_layers).

Applies to code without an ORM or to direct queries. With a framework, use its data layer and drop to raw SQL only with binding: Laravel query builder/Eloquent (`DB::select('... where id = ?', [$id])`), Symfony Doctrine ORM/DBAL, Laminas `laminas-db` or Doctrine. In growing framework-less projects, consider Doctrine DBAL or a query builder before writing hand-concatenated SQL. The rules on parameters, identifiers, transactions and `utf8mb4` apply to all of them.

## Contents
1. Connection
2. Queries
3. Transactions
4. Dynamic queries
5. Code organization
6. UTF-8 and MySQL

## 1. Connection

```php
$pdo = PDO::connect(                                   // 8.4: returns Pdo\Mysql, Pdo\Pgsql, Pdo\Sqlite…
    'mysql:host=db;port=3306;dbname=app;charset=utf8mb4',
    $settings->dbUser,
    $settings->dbPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,   // default since 8.0, make it explicit anyway
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,           // real prepared statements, native types on read
    ],
);
```

- With PHP < 8.4 use `new PDO(...)` with the same options.
- Since 8.4 driver-specific constants live in the subclasses (`Pdo\Mysql::ATTR_USE_BUFFERED_QUERY` instead of `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY`); the old ones still work but are being phased out.
- `charset=utf8mb4` in the DSN: without it, the connection may use a charset different from the tables'.
- Create the connection once per request, in the container, and inject it. Credentials come from configuration, never from `$_ENV` read inside classes.
- Persistent connections (`ATTR_PERSISTENT`) only with a measured justification: they share state (open transactions, session variables) across requests.

## 2. Queries

```php
$stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = :id AND active = :active');
$stmt->execute(['id' => $id, 'active' => 1]);
$user = $stmt->fetch();                 // array|false

$names = $pdo->prepare('SELECT name FROM users WHERE team_id = ?');
$names->execute([$teamId]);
$list = $names->fetchAll(PDO::FETCH_COLUMN);        // list<string>

$map = $pdo->query('SELECT id, name FROM teams')->fetchAll(PDO::FETCH_KEY_PAIR); // [id => name]
```

- Every external value is passed as a bound parameter. `query()` only for fully static SQL.
- `fetch()` returns `false` when there are no rows: handle it explicitly.
- With emulated prepares disabled, `LIMIT :n` works with `execute(['n' => $n])` on MySQL; with emulation enabled you need `bindValue(':n', $n, PDO::PARAM_INT)`.
- Hydrate objects explicitly (`array_map(User::fromRow(...), $rows)`) instead of `FETCH_CLASS`, which writes properties without going through the constructor and ignores `readonly` and validation.
- Large volumes: iterate over the statement (`foreach ($stmt as $row)`) instead of `fetchAll()`; on MySQL disable buffering (`Pdo\Mysql::ATTR_USE_BUFFERED_QUERY => false`) to avoid loading everything into memory.

## 3. Transactions

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

- Encapsulate the pattern in a method (`$db->transactional(fn() => ...)`) instead of repeating it.
- In MySQL, DDL statements (`CREATE`, `ALTER`) perform an implicit commit: do not put them in a transaction expecting a rollback.
- Keep transactions short: no HTTP calls or slow I/O inside them.

## 4. Dynamic queries

**`IN` clause**
```php
$placeholders = implode(', ', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM users WHERE id IN ($placeholders)");
$stmt->execute(array_values($ids));
```
Handle the `$ids === []` case separately (invalid SQL).

**Optional filters**: build static SQL fragments and the parameter array together; no value ends up in the string.
```php
$where = ['1 = 1'];
$params = [];
if ($filter->email !== null) {
    $where[] = 'email = :email';
    $params['email'] = $filter->email;
}
$sql = 'SELECT * FROM users WHERE ' . implode(' AND ', $where);
```

**Identifiers** (columns, tables, `ORDER BY`): only from a whitelist (example in `security.md`).

**`LIKE`**: `$params['q'] = '%' . addcslashes($term, '%_\\') . '%';` if the user must not be able to use wildcards.

## 5. Code organization

- SQL confined to dedicated classes (repositories or query objects) that receive `PDO` in the constructor and return domain types, not `PDOStatement` or raw arrays.
- The domain depends on an interface (`UserRepository`) only if there is a concrete reason (multiple implementations, tests without a database); otherwise the concrete class is enough.
- Never SQL in templates or in controllers/handlers.
- Integration tests for repositories against a real database (in-memory SQLite only if the SQL dialect is compatible, otherwise a container with MySQL/PostgreSQL).
- Versioned migrations (Doctrine Migrations, Phinx) instead of manual SQL scripts.

## 6. UTF-8 and MySQL

- Databases, tables and columns in `utf8mb4` (MySQL `utf8`/`utf8mb3` does not contain emoji and part of the Unicode characters). Collation `utf8mb4_0900_ai_ci` on MySQL 8, `utf8mb4_unicode_ci` on earlier versions and MariaDB.
- Charset in the DSN (section 1): it is the connection that determines how data is transmitted.
