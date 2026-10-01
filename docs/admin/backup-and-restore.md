# Backup and restore

*Radius Hotel Booking — for the hotel's administrator and the hosting provider.*
*Version française plus bas.*

## What the plugin exports for you

**Exports** (Hotel dashboard → Exports) makes spreadsheet files you can keep:

- **Bookings** for any period, by arrival date or by booking date. There is one row per booked
  room, with every status (cancelled and no-show included). Each row carries the guest, the
  room, the stay, the price and the payment summary. The first 31 columns are the ones the old
  "Booking Backup" file had, in the same order, so existing spreadsheets keep working.
- **Guests**: the whole guest list, with identity documents.
- **Reports**: each report has its own **Export** button.

Files are CSV (comma or semicolon, Settings → Exports). With Radius Hotel Booking Pro they can
also be Excel (XLSX). Each file is listed in the **file library** with its size, its row count
and a SHA-256 checksum. Staff download and delete files there, if their access allows it.
Every export, download and deletion is recorded in the activity log.

**An export is a copy of your data, not a backup of the site.** It cannot be imported back to
recreate bookings. Use it to keep records, to share with an accountant, or to check figures in
a spreadsheet.

## What the host must back up

To restore the site after a failure, you need a regular **server backup**. Most hosts offer
this, or you can use a backup plugin. The backup must include:

1. **The database**: the whole WordPress database, which includes every table that starts with
   `wp_radius_hotel_booking_` (the prefix may differ). This holds the bookings, guests,
   payments, invoices, rooms and settings.
2. **The protected files folder**: `wp-content/uploads/radius-hotel-booking/`, which holds the
   export files, generated documents and staff documents. With Pro it also holds the
   **activity log key** (`.activity-log-key`, a hidden file). Without that key, the log can no longer prove that its
   entries were never changed. If `RTBP_PROTECTED_DIR` is set in `wp-config.php`, back up that
   folder instead.
3. **`wp-config.php`**, which holds the keys WordPress needs (and `RTBP_PRO_LOG_KEY` if you
   chose to keep the log key there).
4. The rest of `wp-content` (themes, plugins, media), as for any WordPress site.

Keep at least one copy **away from the server**. Keep backups for longer than the period you may
need to look back on, for example 13 months to cover a full year of comparisons.

## Keep the files private

Export files hold guests' identity documents. The plugin stores them with random names and
serves them only to signed-in staff with the right access.

- On **Apache** hosts, the folder's `.htaccess` blocks direct access.
- On **nginx** hosts, `.htaccess` is ignored. Ask the host to add this rule to the server
  configuration:

  ```nginx
  location ^~ /wp-content/uploads/radius-hotel-booking/ { deny all; return 404; }
  ```

  Alternatively, set `RTBP_PROTECTED_DIR` in `wp-config.php` to a folder outside the website.

Delete files from the library once you no longer need them. Downloaded copies are your
responsibility: do not leave them on shared computers.

## Restoring

1. Restore the database and the files from the same backup (same date), so that the files and
   their rows in the database match.
2. Restore `wp-content/uploads/radius-hotel-booking/` (or the `RTBP_PROTECTED_DIR` folder) with
   the key file, if you use Pro.
3. Sign in, open the dashboard and check a recent booking, its invoice and the file library.

## Archive and remove (Pro)

Radius Hotel Booking Pro can **archive and remove** old bookings. It writes the export file,
checks its checksum, reads every row back from it, and only then deletes the finished stays of
the period from the live database. A booking is removed only if all its rows are in the file.
Future and in-house stays are never removed, and only an administrator can delete an archive
file. Before you use it, **make sure a server backup exists**. The archive file is then the
only copy of those bookings inside the plugin.

---

# Sauvegarde et restauration

*Radius Hotel Booking — pour l'administrateur de l'hôtel et l'hébergeur.*

## Ce que l'extension exporte pour vous

**Exports** (Tableau de bord de l'hôtel → Exports) crée des fichiers de tableur à conserver :

- **Réservations** pour toute période, par date d'arrivée ou par date de réservation. Il y a une
  ligne par chambre réservée, quel que soit le statut (annulations et absences comprises).
  Chaque ligne contient le client, la chambre, le séjour, le prix et le résumé du paiement. Les
  31 premières colonnes sont celles de l'ancien fichier « Booking Backup », dans le même ordre,
  pour que vos tableurs actuels continuent de fonctionner.
- **Clients** : toute la liste des clients, avec les pièces d'identité.
- **Rapports** : chaque rapport a son propre bouton **Exporter**.

Les fichiers sont au format CSV (virgule ou point-virgule, Réglages → Exports). Avec Radius
Hotel Booking Pro, ils peuvent aussi être au format Excel (XLSX). Chaque fichier figure dans la
**bibliothèque de fichiers** avec sa taille, son nombre de lignes et une empreinte SHA-256. Le
personnel y télécharge et supprime les fichiers, si ses droits le permettent. Chaque export,
téléchargement et suppression est noté dans le journal d'activité.

**Un export est une copie de vos données, pas une sauvegarde du site.** On ne peut pas le
réimporter pour recréer des réservations. Il sert à garder une trace, à transmettre des chiffres
au comptable ou à vérifier des chiffres dans un tableur.

## Ce que l'hébergeur doit sauvegarder

Pour restaurer le site après une panne, il faut une **sauvegarde régulière du serveur**. La
plupart des hébergeurs la proposent, ou une extension de sauvegarde peut s'en charger. Elle doit
comprendre :

1. **La base de données** : toute la base WordPress, y compris chaque table qui commence par
   `wp_radius_hotel_booking_` (le préfixe peut varier). Elle contient les réservations, les
   clients, les paiements, les factures, les chambres et les réglages.
2. **Le dossier des fichiers protégés** : `wp-content/uploads/radius-hotel-booking/`. Il contient
   les fichiers d'export, les documents générés et les documents du personnel. Avec Pro, il
   contient aussi la **clé du journal d'activité** (`.activity-log-key`, un fichier caché). Sans cette clé, le journal ne
   peut plus prouver que ses entrées n'ont jamais été modifiées. Si `RTBP_PROTECTED_DIR` est
   défini dans `wp-config.php`, sauvegardez plutôt ce dossier-là.
3. **`wp-config.php`**, qui contient les clés dont WordPress a besoin (et `RTBP_PRO_LOG_KEY`, si
   vous y avez placé la clé du journal).
4. Le reste de `wp-content` (thèmes, extensions, médias), comme pour tout site WordPress.

Gardez au moins une copie **hors du serveur**. Conservez les sauvegardes plus longtemps que la
période sur laquelle vous pourriez revenir, par exemple 13 mois pour comparer une année entière.

## Garder les fichiers privés

Les fichiers d'export contiennent les pièces d'identité des clients. L'extension les enregistre
sous des noms aléatoires et ne les remet qu'au personnel connecté qui a les bons droits.

- Sur les serveurs **Apache**, le fichier `.htaccess` du dossier bloque l'accès direct.
- Sur les serveurs **nginx**, `.htaccess` est ignoré. Demandez à l'hébergeur d'ajouter cette
  règle à la configuration du serveur :

  ```nginx
  location ^~ /wp-content/uploads/radius-hotel-booking/ { deny all; return 404; }
  ```

  Vous pouvez aussi définir `RTBP_PROTECTED_DIR` dans `wp-config.php` vers un dossier situé hors
  du site.

Supprimez de la bibliothèque les fichiers dont vous n'avez plus besoin. Les copies téléchargées
sont sous votre responsabilité : ne les laissez pas sur des ordinateurs partagés.

## Restaurer

1. Restaurez la base de données et les fichiers à partir de la même sauvegarde (même date), pour
   que les fichiers et leurs lignes dans la base correspondent.
2. Restaurez `wp-content/uploads/radius-hotel-booking/` (ou le dossier `RTBP_PROTECTED_DIR`), avec
   le fichier de clé si vous utilisez Pro.
3. Connectez-vous, ouvrez le tableau de bord et vérifiez une réservation récente, sa facture et la
   bibliothèque de fichiers.

## Archiver et supprimer (Pro)

Radius Hotel Booking Pro peut **archiver et supprimer** les anciennes réservations. Il écrit le
fichier d'export, vérifie son empreinte, en relit chaque ligne, et seulement ensuite supprime de
la base les séjours terminés de la période. Une réservation n'est supprimée que si toutes ses
lignes figurent dans le fichier. Seul un administrateur peut supprimer un fichier d'archive. Les séjours à venir et en cours ne sont jamais supprimés. Avant de
l'utiliser, **vérifiez qu'une sauvegarde du serveur existe**. Le fichier d'archive est alors la
seule copie de ces réservations dans l'extension.
