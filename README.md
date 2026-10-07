# Codex

Bibliothèque narrative pour l'écriture d'un livre : elle stocke les connaissances de l'histoire (personnages, lieux, systèmes…) et sa chronologie, et les expose via une API que l'IA pourra interroger pendant l'écriture (plus tard via un serveur MCP).

## Structure du dépôt

| Dossier | Contenu |
|---|---|
| `backend/` | API Symfony (PHP, Doctrine, MySQL). Toutes les commandes `php bin/console` et `composer` se lancent depuis ce dossier. |
| `frontend/` | Application Vue (étape 3), qui communique avec l'API. |

Les chemins `src/`, `config/` et `migrations/` cités plus bas sont ceux de `backend/`.

## Stack

- PHP 8.4+, **Symfony 8.1 skeleton** (`symfony/skeleton`, pas le webapp-pack)
- **Doctrine ORM + Doctrine Migrations**
- **MySQL 8** (WAMP en local, MySQL en production), tables en **InnoDB**
- Pas d'API Platform : contrôleurs JSON écrits à la main
- Déploiement : VPS Debian, nginx + PHP-FPM, Certbot

Paquets installés : `symfony/orm-pack`, `symfony/serializer`, `symfony/validator`, `nelmio/cors-bundle`, et en dev `symfony/maker-bundle`.

## Installation locale

1. `cd backend` puis `composer install`
2. Créer `backend/.env.local` (non versionné) avec l'URL de la base :
   `DATABASE_URL="mysql://root:@127.0.0.1:3306/codex?serverVersion=8.0&charset=utf8mb4"`
3. **Production uniquement** : définir un `APP_SECRET` aléatoire de 64 caractères (`php -r "echo bin2hex(random_bytes(32));"`) dans le `backend/.env.local` du serveur ou en variable d'environnement. Il signe les cookies de connexion : il est **obligatoire** en production (le `.env` versionné le laisse vide) et le changer déconnecte tout le monde. En développement, `backend/.env.dev` (versionné, valeur sans importance) en fournit un.
4. `php bin/console doctrine:database:create --if-not-exists`
5. `php bin/console doctrine:migrations:migrate`
6. `php bin/console app:user:create <identifiant>` pour créer son compte (demande le mot de passe), puis éventuellement `php bin/console app:seed <identifiant>` pour un livre d'exemple.

**InnoDB obligatoire** : le MySQL de WAMP crée les tables en MyISAM par défaut, qui ignore les clés étrangères et `ON DELETE CASCADE`. `default_table_options: engine: InnoDB` est donc forcé dans `config/packages/doctrine.yaml`.

## Conventions

- **Uniquement de l'anglais dans le code** : identifiants (classes, propriétés, colonnes, routes), commentaires, messages de validation et d'erreur de l'API.
- Abstraction minimale : pas de couches inutiles, pas de patterns sur-ingénierés.
- Code complet et prêt à copier.
- **Trois couches, chacune avec un seul rôle** :
  - **Contrôleur** (`Controller/`) : lit la requête (corps JSON, paramètres d'URL), appelle un service, renvoie la réponse JSON avec son code HTTP. Aucune règle métier, aucune requête, pas d'`EntityManager`.
  - **Service** (`Service/`) : toute la logique métier d'une ressource (validation, règles, persistance, mise en forme). Il ne connaît pas HTTP : il reçoit des valeurs simples, renvoie des tableaux prêts à devenir du JSON et lance des `ApiException`.
  - **Repository** (`Repository/`) : toutes les requêtes de lecture, en DQL ou en SQL direct (DBAL) quand c'est plus simple.
- Organisation de `src/` : `Entity/` (mapping Doctrine et contraintes), `Repository/`, `Service/` (`KnowledgeService`, `EventService`, `EventParticipantService`, plus `Hydrator` qui applique les champs sur une entité et `Paginator` qui exécute une liste paginée), `Validation/Validate.php` (toute la validation : champs acceptés et types par ressource, contraintes d'entité, paramètres de requête), `Api/` (`ApiHelper` pour lire un corps JSON et écrire une réponse, `Page`, `BookResolver`), `Error/` (codes d'erreur), `EventListener/`, `Command/`, `Controller/`. Un nouveau champ se déclare dans l'entité, dans la constante correspondante de `Validate` et dans la méthode `serialize` du service.
- Performance : index sur toutes les clés étrangères et colonnes filtrées, réponses courtes par défaut, pas de cache tant qu'une mesure ne le justifie pas.

## Modèle de données

Un **compte** (`user`) possède des **livres** (`book`). Chaque livre contient deux groupes de données :
- **Connaissances** : la table `knowledge`, une seule table pour toutes les catégories (personnage, lieu, système…). Ajouter une catégorie = ajouter une valeur de `type` (constante `Knowledge::TYPES`), sans changer la structure.
- **Chronologie** : la table `event`, reliée aux connaissances par `event_participant`.

Les connaissances et les événements ont une **clé technique** entière (jamais exposée) et un **slug** lisible, unique dans un livre : l'API l'expose sous le nom `id` (`aldric`, `evt-0042`). Deux livres peuvent donc contenir chacun un `aldric`.

### User (`user`)
| Champ | Type | Notes |
|---|---|---|
| id | int (PK) | |
| username | string | unique, 3 à 50 caractères |
| password | string | hash (bcrypt/argon), jamais le mot de passe |
| roles | json | `ROLE_USER` est toujours ajouté |
| createdAt | datetime_immutable | |

### Book (`book`)
| Champ | Type | Notes |
|---|---|---|
| id | int (PK) | |
| user | ManyToOne User | onDelete CASCADE |
| name | string | |
| createdAt / updatedAt | datetime_immutable | |

Supprimer un compte supprime ses livres ; supprimer un livre supprime tout son contenu et ses clés d'API (cascade en base).

### ApiKey (`api_key`)
Donne à une IA ou à un script l'accès à **un** livre.
| Champ | Type | Notes |
|---|---|---|
| id | int (PK) | |
| user | ManyToOne User | onDelete CASCADE |
| book | ManyToOne Book | onDelete CASCADE |
| name | string | libellé libre : « ChatGPT », « Claude »… |
| tokenHash | string | SHA-256 du jeton, unique ; le jeton n'est jamais stocké |
| prefix | string | début du jeton (`cdx_` + 8 caractères), pour reconnaître la clé dans une liste |
| scope | string | `read` ou `write` |
| createdAt | datetime_immutable | |
| lastUsedAt | datetime_immutable, nullable | rafraîchi au plus toutes les 5 minutes |

### Knowledge (`knowledge`)
| Champ | Type | Notes |
|---|---|---|
| id | int (PK, auto) | clé technique, non exposée |
| book | ManyToOne Book | onDelete CASCADE |
| slug | string | exposé comme `id` : `aldric`, `citadelle-nord` ; unique avec le livre |
| type | string | `character`, `place`, `system` |
| name | string | |
| summary | text | 2-3 phrases, ce que l'IA lit en premier |
| description | text, nullable | version longue, chargée à la demande |
| aliases | json | ex. `["le Borgne"]` |
| createdAt / updatedAt | datetime_immutable | |

Index : unique `(book_id, slug)`, `(book_id, type)`.

### Event (`event`)
| Champ | Type | Notes |
|---|---|---|
| id | int (PK, auto) | clé technique, non exposée |
| book | ManyToOne Book | onDelete CASCADE |
| slug | string | exposé comme `id` : `evt-0042` ; unique avec le livre |
| title | string | |
| summary | text | |
| detail | text, nullable | |
| worldOrder | int | chronologie interne, sert au tri |
| worldDate | string, nullable | date optionnelle, affichage seul : `An 312, hiver` |
| chapter | int, nullable | numéro du chapitre où c'est raconté ; null = hors-champ |
| revealed | bool, défaut true | le lecteur le sait-il ? |
| tags | json | |
| createdAt / updatedAt | datetime_immutable | |

Index : unique `(book_id, slug)`, `(book_id, chapter, world_order)` et `(book_id, world_order)`.

Un chapitre peut contenir plusieurs événements ; il n'y a pas de table `chapter` (à ajouter plus tard si on veut un titre ou un résumé par chapitre).

### EventParticipant (`event_participant`)
Lien événement ↔ connaissance (personnage, lieu, système…), avec un rôle. L'API l'adresse par les slugs : `eventId` et `knowledgeId`.
| Champ | Type | Notes |
|---|---|---|
| event | ManyToOne Event | PK composite, onDelete CASCADE |
| knowledge | ManyToOne Knowledge | PK composite, onDelete CASCADE |
| role | string, nullable | `author`, `victim`, `witness`, `place`… |

Index : PK `(event_id, knowledge_id)` et `(knowledge_id, event_id)`. L'événement et la connaissance doivent appartenir au même livre : l'API les cherche dans le livre de l'URL, donc un lien entre deux livres est impossible (`REFERENCE_NOT_FOUND`).

Points clés :
- `worldOrder` est un entier car les dates de fiction ne se trient pas ; `worldDate` n'est qu'un affichage optionnel.
- `summary` séparé de `description`/`detail` pour que l'IA reçoive des réponses courtes d'abord.
- `revealed` sépare la vérité du monde de ce que le lecteur sait déjà.
- Deux axes de temps indépendants : `worldOrder` (quand ça arrive dans le monde) et `chapter` (quand le lecteur le découvre).
- Les relations entre connaissances (alliée, ennemie…) ne sont pas dans la v1 ; elles pourront être ajoutées plus tard dans une table `relation` bornée par des événements.

## Index pour l'IA

`GET /api/books/{bookId}/index` renvoie la liste légère de toutes les connaissances d'un livre : `id`, `name`, `type`, `aliases` (filtre optionnel `?type=`). L'IA la charge une fois, résout un nom ou un alias vers un `id`, puis appelle directement `GET /api/books/{bookId}/knowledge/{id}`. Une seule requête SQL, cachable avec un ETag.

## Étape 1 : API CRUD (terminée)

Fait : projet Symfony, entités Doctrine, migration, codes d'erreur, listeners (erreurs, auth), `/health`, `/api/errors`, CORS, contrôleurs CRUD (knowledge, events, event-participants) et index. Les routes ci-dessous ont été déplacées sous un livre à l'étape 2.
Reste : rien, l'étape 1 est terminée.

Préfixe `/api`, JSON uniquement. `{bookId}` est l'identifiant numérique du livre ; un livre inconnu renvoie `BOOK_NOT_FOUND`. `id` désigne toujours le slug.

| Ressource | Collection | Élément |
|---|---|---|
| Connaissances | `/api/books/{bookId}/knowledge` | `/api/books/{bookId}/knowledge/{id}` |
| Événements | `/api/books/{bookId}/events` | `/api/books/{bookId}/events/{id}` |
| Participants | `/api/books/{bookId}/event-participants` | `/api/books/{bookId}/event-participants/{eventId}/{knowledgeId}` |
| Index | `/api/books/{bookId}/index` | |

- `GET` collection : `?limit=50&offset=0` (max 200) + filtres exacts
  - knowledge : `type`
  - events : `chapter`, `revealed` (tri par `worldOrder`)
  - event-participants : `eventId`, `knowledgeId`, `role`
  - réponse : `{ "data": [...], "total": n, "limit": n, "offset": n }`
- Les collections ne lisent que les colonnes courtes (requêtes en tableau, sans hydrater d'entités) : `id`, `name`, `type`, `summary` pour knowledge ; `id`, `title`, `summary`, `worldOrder`, `worldDate`, `chapter`, `revealed` pour events. `description` et `detail` ne sortent qu'avec le `GET` d'un élément.
- `POST` et `PATCH` refusent les champs inconnus ou du mauvais type (`VALIDATION_FAILED`, tous les champs fautifs d'un coup). `id` n'est accepté qu'à la création. Pour un participant, `POST` prend `{eventId, knowledgeId, role}` et `PATCH` ne modifie que `role`.
- Supprimer une connaissance ou un événement supprime ses liens de participation (`ON DELETE CASCADE` en base).
- `GET` élément, `POST` (201), `PATCH` (mise à jour partielle), `DELETE` (204)

**Validation** : contraintes Symfony Validator sur les entités (messages en anglais) ; 400 avec la liste des erreurs par champ.

**Erreurs** : chaque erreur a un code stable, un statut HTTP, une description et une solution, tous définis dans l'enum `App\Error\ErrorCode` (source unique de vérité). Les contrôleurs lancent `new ApiException(ErrorCode::KNOWLEDGE_NOT_FOUND)` ; le listener `kernel.exception` produit la réponse JSON et convertit aussi les erreurs natives de Symfony (route inconnue, méthode non autorisée). Les erreurs 500 sont écrites dans les logs, jamais exposées.

```json
{ "error": { "code": "VALIDATION_FAILED", "message": "The submitted data is invalid.",
             "details": { "fields": { "name": ["The name is required."] } } } }
```

`GET /api/errors` (public) liste tous les codes avec leur description et leur solution, générés depuis l'enum. Ajouter un code = ajouter un cas à l'enum et remplir ses quatre méthodes (`status`, `message`, `description`, `solution`) : le code ne compile pas sans.

| Code | HTTP | Quand |
|---|---|---|
| `INVALID_JSON` | 400 | corps absent, illisible, non UTF-8, qui n'est pas un objet JSON, ou login sans `username` / `password` |
| `VALIDATION_FAILED` | 400 | un champ enfreint une contrainte (`details.fields`) |
| `INVALID_QUERY_PARAMETER` | 400 | `limit`, `offset` ou filtre inutilisable (`details.parameter`) |
| `UNAUTHORIZED` | 401 | ni session valide ni clé d'API valide |
| `LOGIN_FAILED` | 401 | identifiant inconnu ou mot de passe faux (même réponse dans les deux cas) |
| `FORBIDDEN` | 403 | clé en lecture seule qui écrit, ou clé sur une action réservée à une session |
| `ROUTE_NOT_FOUND` | 404 | aucune route pour cette URL |
| `BOOK_NOT_FOUND` | 404 | livre inconnu, ou qui n'appartient pas au compte ou à la clé utilisés |
| `KNOWLEDGE_NOT_FOUND` | 404 | identifiant de connaissance inconnu |
| `EVENT_NOT_FOUND` | 404 | identifiant d'événement inconnu |
| `PARTICIPANT_NOT_FOUND` | 404 | lien événement ↔ connaissance inexistant |
| `API_KEY_NOT_FOUND` | 404 | clé d'API inconnue ou qui appartient à un autre compte |
| `METHOD_NOT_ALLOWED` | 405 | méthode refusée sur cette route (header `Allow`) |
| `ID_ALREADY_EXISTS` | 409 | identifiant ou lien déjà pris |
| `REFERENCE_NOT_FOUND` | 409 | un champ pointe vers une ressource inexistante |
| `TOO_MANY_ATTEMPTS` | 429 | trop de connexions échouées (header `Retry-After`) |
| `INTERNAL_ERROR` | 500 | erreur serveur inattendue |

**Auth** : comptes, sessions et clés d'API, décrits à l'étape 2. Seuls `GET /health`, `GET /api/errors` et `POST /api/auth/login` sont publics.


**CORS** : `nelmio/cors-bundle`, origines via `CORS_ALLOW_ORIGIN`.

**Données d'exemple** : `php bin/console app:seed <username>` charge dans le livre « Example Book » du compte indiqué Aldric, Mira, la Citadelle du Nord (`citadelle-nord`), les événements « The Oath » (`evt-0041`, ordre 41, ch. 3) et « The Council's Betrayal » (`evt-0042`, ordre 42, ch. 7) et leurs 6 participations. Les textes sont en anglais (règle du code). La commande est idempotente (elle ne crée que ce qui manque) ; `--reset` supprime d'abord ce livre d'exemple et son contenu (jamais les autres livres), après confirmation.

## Feuille de route

Décisions prises :
- **Plusieurs livres.** Un livre appartient à un seul compte ; pour partager un livre, on partage le compte.
- **Comptes créés uniquement par commande** (identifiant + mot de passe), pas d'inscription publique. Sessions de très longue durée, les données étant peu sensibles.
- **Slug unique par livre** : clé technique en base, `id` lisible (slug) côté API.
- **IA via un serveur MCP distant** (ChatGPT n'accepte que des serveurs HTTPS publics). ChatGPT Pro ne peut pas écrire par MCP : l'app Vue fournit donc les consignes à copier et un **import en masse** du JSON généré par l'IA. Claude pourra écrire par MCP.
- **Pas de chat dans l'app Vue** (trop lourd à construire, le MCP couvre le besoin).

### Étape 2 : comptes, livres et clés d'API (terminée)

**Fait** : tables `user`, `book` et `api_key`, clés techniques et slugs par livre, routes de contenu sous `/api/books/{bookId}/`, connexion avec session d'un an, clés d'API liées à un livre, contrôle des droits, commandes de gestion des comptes.

**Routes** (le contenu d'un livre est décrit à l'étape 1 ; `id` reste le slug) :

| Zone | Routes | Accès |
|---|---|---|
| Public | `GET /health`, `GET /api/errors`, `POST /api/auth/login` (`username`, `password`) | tout le monde |
| Session | `POST` ou `GET /api/auth/logout`, `GET /api/auth/me` | `me` : session ou clé ; `logout` : session |
| Livres | `GET` et `POST /api/books`, `PATCH` et `DELETE /api/books/{bookId}` | session uniquement |
| Livre | `GET /api/books/{bookId}` | session, ou clé de ce livre |
| Contenu | `/api/books/{bookId}/knowledge`, `/events`, `/event-participants`, `/index` | session, ou clé de ce livre (écriture : clé `write`) |
| Clés | `GET` et `POST /api/books/{bookId}/api-keys`, `DELETE /api/api-keys/{id}` | session uniquement |

**Deux façons de s'authentifier**, sur deux pare-feux Symfony (`config/packages/security.yaml`) :
- **Session** (app Vue) : `POST /api/auth/login` avec `{"username", "password"}`. Le serveur pose un cookie de session et un cookie « remember me » d'**un an** (`HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS) : même si la session PHP expire, le cookie rétablit la connexion. Après 5 échecs en 15 minutes, la connexion est bloquée (`TOO_MANY_ATTEMPTS`, `Retry-After`), même avec le bon mot de passe.
- **Clé d'API** (IA, scripts) : header `X-API-Key`. Ces requêtes passent par un pare-feu **sans état** (`ApiKeyRequestMatcher`) : aucune session, aucun cookie. Une clé invalide donne `UNAUTHORIZED`, sans retomber sur une éventuelle session.

**Clés d'API** : une clé est liée à **un livre** et à un `scope` (`read` ou `write`) ; l'IA n'a jamais à choisir de livre, et une clé qui fuit n'expose qu'un livre. Format `cdx_` + 40 caractères hexadécimaux, **affichée une seule fois** à la création (champ `token` de la réponse) ; seul son hash SHA-256 est stocké, la liste ne montre que le préfixe. `DELETE /api/api-keys/{id}` la supprime immédiatement ; la supprimer avec son livre ou son compte est automatique.

**Droits**, appliqués à un seul endroit (`BookResolver`) :
- une session accède aux livres de son compte ;
- une clé accède au livre pour lequel elle a été créée, et ne peut le modifier qu'avec le `scope` `write` (sinon `FORBIDDEN`) ;
- un livre inaccessible est toujours signalé `BOOK_NOT_FOUND`, même s'il existe : on ne peut pas deviner quels livres existent ;
- l'attribut `#[SessionOnly]` (vérifié par `SessionOnlyListener`) réserve une action à une session : gérer les livres et les clés d'API est interdit à une clé, qui ne peut donc ni créer d'autres clés ni supprimer un livre.

**Commandes** :
- `php bin/console app:user:create <username>` : crée un compte (3 à 50 caractères, mot de passe de 8 caractères minimum demandé en saisie masquée, hashé en base). C'est la seule façon de créer un compte.
- `php bin/console app:user:password <username>` : change un mot de passe.
- `php bin/console app:seed <username>` : charge le livre d'exemple (voir plus haut).

**À savoir** : le header `X-API-Key` fait partie des en-têtes CORS autorisés, avec `allow_credentials` pour les cookies. Les clés d'API nécessitent un corps UTF-8 ; depuis un terminal Windows, `curl.exe` déforme les accents passés en argument (`-d`) : envoyer le corps depuis un fichier (`--data-binary @fichier.json`).

### Étape 3 : application Vue

- **Pile** : Vue 3, Vite, Vue Router, Pinia, Tailwind. Dossier `app/` du dépôt. L'app construite est servie par nginx sur le même domaine que l'API (`/` pour l'app, `/api` pour PHP), donc sans CORS en production.
- **Écrans** : connexion ; choix et création de livre ; bibliothèque (liste filtrable par type, recherche par nom ou alias) ; fiche en édition ; chronologie triée par `worldOrder` ; édition d'événement avec sélecteur de participants alimenté par `/index` ; clés d'API (création, copie unique, révocation, URL MCP prête à copier) ; **import en masse**.

**Import en masse** (pour le JSON généré par ChatGPT) :
1. **Consignes** : un encadré avec un bouton « Copier » donne à coller dans ChatGPT le texte à suivre. Il est généré par le serveur (`GET /api/books/{bookId}/import/instructions`) : rôle demandé, format JSON exact, règles (slugs en minuscules avec tirets, types autorisés, `worldOrder` entier, résumés de 2-3 phrases) et **la liste des slugs déjà présents** pour que l'IA les réutilise sans créer de doublons.
2. **Coller** le JSON renvoyé par l'IA.
3. **Vérifier** : `POST /api/books/{bookId}/import?dryRun=true` renvoie un rapport sans rien écrire (à créer, déjà présents, erreurs avec leur chemin, par exemple `knowledge[2].type`).
4. **Importer** : le même appel sans `dryRun`. L'import est **atomique** : si un élément est invalide, rien n'est écrit. `onExisting=fail|skip|update` choisit le comportement pour les slugs déjà présents (`fail` par défaut).

Format du document (les participants peuvent référencer des éléments du document ou déjà en base) :

```json
{
  "knowledge": [{ "id": "aldric", "type": "character", "name": "Aldric", "summary": "...", "description": "...", "aliases": [] }],
  "events": [{ "id": "evt-0043", "title": "...", "summary": "...", "worldOrder": 43, "chapter": 8 }],
  "participants": [{ "eventId": "evt-0043", "knowledgeId": "aldric", "role": "author" }]
}
```

L'import réutilise les règles de `Validate` ; la taille du document est limitée.

### Étape 4 : routes pour l'IA

Filtre de point de vue temporel `beforeChapter` / `atChapter` sur toutes les lectures, masquage de `revealed = false` par défaut (`includeSecrets=true` pour l'auteur), endpoint timeline, fiche d'une connaissance (avec ses événements) en 2-3 requêtes SQL fixes, recherche plein texte sur le contenu (index `FULLTEXT` MySQL). Les requêtes complexes vont dans les repositories.

### Étape 5 : serveur MCP distant

- Une route de l'application Symfony (`/mcp`) avec le SDK PHP officiel (`mcp/sdk`, transport HTTP Streamable) ; les outils appellent directement les repositories. Le SDK est à revérifier avant de s'engager.
- **Authentification** : d'abord une clé secrète révocable dans l'URL (`https://<hôte>/mcp/<clé>`, la clé désigne l'utilisateur, le livre et le droit lecture ou écriture). ChatGPT n'impose pas OAuth (il accepte aussi le mode sans authentification), donc la clé dans l'URL lui suffit. Claude.ai utilise OAuth 2.1 : OAuth ne sera ajouté que si Claude le refuse (à tester).
- **Outils en lecture** : `index`, `get_knowledge`, `get_knowledge_events`, `timeline`, `get_event`, `search`.
- **Outils en écriture** (clé `write`, pour Claude) : `create_knowledge`, `update_knowledge`, `create_event`, `update_event`, `link_participant`, avec les mêmes validations que l'API. Pas d'outil de suppression : elle reste réservée à l'app. Les clients MCP demandent une confirmation avant chaque écriture.
- ChatGPT Pro reste en lecture seule ; ses ajouts passent par l'import en masse de l'étape 3.

### Plus tard

Table `relation` bornée dans le temps, recherche sémantique, éventuellement un chat intégré à l'app (il réutiliserait les mêmes outils que le MCP).
