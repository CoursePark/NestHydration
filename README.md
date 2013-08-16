NestHydration
=============

Usage Example
-------------

```
$dbh = new PDO("mssql:host=$host;dbname=$dbname, $user, $pass");
\NestHydration\NestHydration::nest($dbh->query('
    SELECT
        u.id            "_id",
        u.firstname     "_firstname",
        u.lastname      "_lastname",
        pe.id           "_primaryEmail_id",
        pe.email        "_primaryEmail_email",
        e.id            "_email__id",
        e.email         "_email__email"
    FROM cp_user u
    JOIN cp_email pe ON u.primary_email_id = pe.id
    JOIN cp_user_email ue ON u.id = ue.user_id
    JOIN cp_email e ON ue.email_id = e.id
    WHERE u.id IN (1, 2, 3, 4)
    ORDER BY u.id ASC, e.id ASC
    ')
    ->fetchAll(PDO::FETCH_ASSOC)
);
```
