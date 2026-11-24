# Backend (Laravel)

API REST de la plateforme de rendez-vous. Voir `../CLAUDE.md` et `../docs/`.

```
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan test      # nécessite PostgreSQL (base easycalendar_test, voir phpunit.xml)
vendor/bin/pint       # lint
```

Ou tout lancer avec Docker depuis la racine : `docker compose up --build`.
