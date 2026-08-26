# Base de données — OL Creative Studio

## Fichiers

- `schema.sql` : structure complète de la base de données ;
- `seed-content.sql` : contenu public initial (`portfolio` et `avis`).

## Installation en production

1. Créer la base de données sur l’hébergement.
2. Importer `schema.sql`.
3. Importer `seed-content.sql`.
4. Créer le compte administrateur directement en production.
5. Configurer les variables d’environnement :

```env
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=