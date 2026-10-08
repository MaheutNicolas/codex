# Codex

Bibliothèque narrative pour l'écriture d'un livre : elle stocke les connaissances de l'histoire (personnages, lieux, systèmes…) et sa chronologie, et les expose via une API que l'IA pourra interroger pendant l'écriture (plus tard via un serveur MCP).

## Structure du dépôt

| Dossier | Contenu |
|---|---|
| `backend/` | API Symfony (PHP, Doctrine, MySQL). Toutes les commandes `php bin/console` et `composer` se lancent depuis ce dossier. |
| `frontend/` | Application Vue 3 (Vite, SCSS, JavaScript) qui communique avec l'API. Les commandes `npm` se lancent depuis ce dossier. |

Les chemins `src/`, `config/` et `migrations/` cités plus bas sont ceux de `backend/`.

## Stack

- PHP 8.4+, **Symfony 8.1 skeleton** (`symfony/skeleton`, pas le webapp-pack)
- **Doctrine ORM + Doctrine Migrations**
- **MySQL 8** (WAMP en local), ou **MariaDB 10.11+** (essayée en 11.4 : voir `DEPLOY.md`), tables en **InnoDB**
- Pas d'API Platform : contrôleurs JSON écrits à la main
- Déploiement : VPS Debian, nginx + PHP-FPM, Certbot (voir [DEPLOY.md](DEPLOY.md))

Paquets installés : `symfony/orm-pack`, `symfony/serializer`, `symfony/validator`, `nelmio/cors-bundle`, et en dev `symfony/maker-bundle`.

## Installation locale

1. `cd backend` puis `composer install`
2. Créer `backend/.env.local` (non versionné) avec l'URL de la base :
   `DATABASE_URL="mysql://root:@127.0.0.1:3306/codex?serverVersion=8.0&charset=utf8mb4"`
3. **Production uniquement** : définir un `APP_SECRET` aléatoire de 64 caractères (`php -r "echo bin2hex(random_bytes(32));"`) dans le `backend/.env.local` du serveur ou en variable d'environnement. Il signe les cookies de connexion : il est **obligatoire** en production (le `.env` versionné le laisse vide) et le changer déconnecte tout le monde. En développement, `backend/.env.dev` (versionné, valeur sans importance) en fournit un.
4. `php bin/console doctrine:database:create --if-not-exists`
5. `php bin/console doctrine:migrations:migrate`
6. `php bin/console app:user:create <identifiant>` pour créer son compte (demande le mot de passe), puis éventuellement `php bin/console app:seed <identifiant>` pour un livre d'exemple.
7. Frontend : voir « Lancer en développement » à l'étape 3 (`cd frontend`, `npm install`, `npm run dev`).

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
Donne à une IA ou à un script l'accès à un livre. **Un livre a exactement une clé** (index unique sur `book_id`), créée à la première demande.
| Champ | Type | Notes |
|---|---|---|
| id | int (PK) | |
| user | ManyToOne User | onDelete CASCADE |
| book | ManyToOne Book | onDelete CASCADE |
| token | string | jeton `cdx_` + 40 caractères hexadécimaux, unique, **stocké tel quel** et toujours affiché à son propriétaire (les données ne sont pas sensibles : si la base fuit, les données fuitent de toute façon) |
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
- Les collections ne lisent que les colonnes courtes (requêtes en tableau, sans hydrater d'entités) : `id`, `name`, `type`, `summary`, `aliases` pour knowledge ; `id`, `title`, `summary`, `worldOrder`, `worldDate`, `chapter`, `revealed` pour events. `description` et `detail` ne sortent qu'avec le `GET` d'un élément.
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
| Clé | `GET /api/books/{bookId}/api-key` (la crée si besoin), `POST /api/books/{bookId}/api-key/regenerate` | session uniquement |

**Deux façons de s'authentifier**, sur deux pare-feux Symfony (`config/packages/security.yaml`) :
- **Session** (app Vue) : `POST /api/auth/login` avec `{"username", "password"}`. Le serveur pose un cookie de session et un cookie « remember me » d'**un an** (`HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS) : même si la session PHP expire, le cookie rétablit la connexion. Après 5 échecs en 15 minutes, la connexion est bloquée (`TOO_MANY_ATTEMPTS`, `Retry-After`), même avec le bon mot de passe.
- **Clé d'API** (IA, scripts) : header `X-API-Key`. Ces requêtes passent par un pare-feu **sans état** (`ApiKeyRequestMatcher`) : aucune session, aucun cookie. Une clé invalide donne `UNAUTHORIZED`, sans retomber sur une éventuelle session.

**Clé d'API** : chaque livre a une seule clé, de portée `read` à la création (l'écriture passe par l'app et, plus tard, l'import). L'IA n'a jamais à choisir de livre, et une clé qui fuit n'expose qu'un livre. Le jeton est visible à tout moment par le propriétaire (`GET .../api-key`). `POST .../api-key/regenerate` le remplace : l'ancien cesse de fonctionner immédiatement, ce qui sert si l'on pense qu'un tiers y a eu accès. La clé disparaît avec son livre ou son compte.

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

### Étape 3 : application Vue (terminée)

**Décisions** : Vue 3 + Vite + Vue Router, en **JavaScript** (pas de TypeScript), **sans Pinia** (l'état partagé tient dans des composables), **sans bibliothèque de composants ni Tailwind** : les styles sont des fichiers SCSS compilés en **un seul fichier CSS**, avec un design épuré, un thème clair et un thème sombre. Tous les textes affichés sont dans un fichier par langue (français et anglais).

**Fait** : projet et outillage, client d'API, connexion (avec redirection vers la page demandée, session qui survit au rechargement), liste des livres (créer, renommer, supprimer avec confirmation), coque de l'application (barre latérale, tiroir sur mobile), menu d'apparence (mode et couleur), notifications, **bibliothèque** (voir ci-dessous), **chronologie** (voir plus bas). **clé d'API** (une seule par livre, toujours affichée, avec copie et régénération après confirmation), **import** (voir plus bas).

**Reste** : rien pour l'étape 3.

**Bibliothèque** (`views/LibraryView.vue`, `components/library/KnowledgePanel.vue`) :
- **Liste** : toutes les fiches du livre (nom, type, alias, résumé), triées par nom, chargées d'un coup (par pages de 200 si besoin). La liste de l'API renvoie les alias pour permettre la recherche.
- **Recherche** instantanée, côté navigateur, **sans tenir compte des accents ni de la casse** (« resonance » trouve « La Résonance »). Elle porte sur le nom, les alias, l'identifiant et le résumé ; les résultats sont classés (nom qui commence par la recherche, nom qui la contient, alias, reste). Les touches `/` et `Ctrl+K` placent le curseur dans la recherche.
- **Filtres par type** (Personnages, Lieux, Systèmes) avec le nombre de fiches de chaque type.
- **Panneau latéral** pour créer ou modifier une fiche, piloté par l'adresse : `?entry=aldric` ouvre une fiche, `?new` ouvre la création, donc un lien direct ou le bouton « précédent » fonctionnent. Champs : nom, type, identifiant, résumé, description, alias (étiquettes : Entrée ou virgule pour ajouter, Retour arrière pour retirer, doublons ignorés).
- **Identifiant** : à la création il se déduit du nom (« Citadelle du Nord » donne `citadelle-du-nord`) tant qu'on ne l'a pas modifié à la main ; il est en lecture seule ensuite. « Enregistrer et créer une autre » enchaîne les saisies sans fermer le panneau.
- **Validation** en français dans le navigateur (champs obligatoires, format de l'identifiant) ; erreur effacée dès que le champ est modifié ; identifiant déjà pris signalé sous le champ.
- **Rien n'est perdu sans prévenir** : fermer le panneau avec des modifications non enregistrées (Échap, clic à côté, croix, Annuler) demande confirmation ; la suppression aussi. Dans les confirmations, le bouton le moins destructeur a le focus.

**Chronologie** (`views/TimelineView.vue`, `components/timeline/EventPanel.vue`) :
- **Liste plate** triée par `worldOrder`, chargée d'un coup : ordre, titre, date du monde, chapitre (ou « Hors-champ »), badge « Secret » si l'événement n'est pas révélé, résumé. Un chapitre peut contenir un ou plusieurs événements.
- **Recherche** sans accents ni casse (titre, résumé, identifiant, date), filtre révélés / secrets et filtre par chapitre (les chapitres présents, plus « Hors-champ »). Les touches `/` et `Ctrl+K` placent le curseur dans la recherche.
- **Réordonner** : des boutons monter / descendre, visibles quand aucun filtre n'est actif. Déplacer un événement échange son `worldOrder` avec celui de son voisin (deux requêtes) ; si les deux sont égaux, les ordres qui cessent de croître sont réécrits. Pour insérer entre deux événements, on peut aussi modifier le nombre à la main.
- **Panneau latéral** piloté par l'adresse (`?event=evt-0042`, `?new`), comme la bibliothèque. À la création, l'identifiant (`evt-NNNN` suivant) et l'ordre (dernier + 1) sont pré-remplis.
- **Participants** : chargés à l'ouverture du panneau seulement (`event-participants?eventId=`), pas dans la liste. Le sélecteur est alimenté par `GET /index`. Les ajouts, retraits et changements de rôle sont envoyés à l'enregistrement, après l'événement.

**Lancer en développement** (deux terminaux) :
- `cd backend` puis `symfony serve` (ou `php -S 127.0.0.1:8000 -t public`) ;
- `cd frontend` puis `npm install` (la première fois) et `npm run dev`, puis ouvrir http://localhost:5173.

Vite redirige `/api` et `/health` vers le backend (`vite.config.js`) : l'app et l'API partagent la même origine, comme en production derrière nginx, donc le cookie de session fonctionne sans configuration CORS. `npm run build` produit `frontend/dist/` (un seul fichier CSS, des fichiers JS par page), à servir par nginx sur `/` avec `/api` envoyé à PHP-FPM.

**Organisation de `frontend/src/`** :

| Dossier | Rôle |
|---|---|
| `styles/` | SCSS : `main.scss` (point d'entrée, ordre des imports), `_theme.scss` (**tout le thème**), `_mixins.scss`, `base/`, `layout/`, `components/`, `pages/` |
| `locales/` | `index.js` (langue active réactive, `t`, `tn`, `setLocale`), `fr.js` et `en.js` : **tous les textes affichés**, y compris un message par code d'erreur de l'API ; `t('books.title')` les lit |
| `api/` | `client.js` (appels `fetch`, erreurs de l'API transformées en `ApiError`, session expirée gérée) et un fichier par ressource |
| `composables/` | état partagé sans Pinia : `useAuth`, `useBook`, `useTheme`, `useToast` |
| `components/ui/` | éléments de base : `UiButton`, `UiField` (champ ou zone de texte), `UiSelect`, `UiTagInput`, `UiDialog` (fenêtre ou panneau latéral ; il ne se ferme jamais seul, il demande à être fermé avec l'événement `dismiss`), `ToastHost` |
| `components/library/` | composants propres à la bibliothèque (`KnowledgePanel`) |
| `components/layout/` | `AppShell`, `AppSidebar`, `ThemeMenu` |
| `views/` | une page par route |
| `constants.js`, `utils/` | types de fiches et leurs icônes (à tenir d'accord avec `Knowledge::TYPES` du backend), formatage de dates, fonctions de texte (`normalize`, `slugify`) |
| `router.js` | routes et garde de connexion |

**Styles** : fichiers SCSS globaux, nommés en BEM avec préfixe (`.c-button--primary`, `.sidebar__link`), jamais de style dans les composants Vue. Aucun fichier SCSS ne contient de couleur : ils lisent des variables CSS.

**Changer l'apparence** : tout est dans `frontend/src/styles/_theme.scss`. Changer `--accent-h` (la teinte, de 0 à 360) change la couleur de toute l'app ; les valeurs `--radius-*` changent les arrondis, `--space-*` la densité, et les deux mixins `palette-light` et `palette-dark` les couleurs de chaque mode. Les cinq couleurs proposées dans le menu « Apparence » (indigo, sarcelle, vert, ambre, rose) sont les blocs `[data-accent]` du même fichier. Le choix est mémorisé dans le navigateur.

**Textes et langues** : français et anglais, dans `locales/fr.js` et `locales/en.js`, qui doivent avoir exactement les mêmes clés et les mêmes `{paramètres}`. La langue se choisit dans le menu « Apparence » (FR / EN), est mémorisée dans le navigateur et, à défaut, suit la langue du navigateur (français si c'est du français, sinon anglais). Elle est réactive : tout change sans recharger. Elle règle aussi les dates, le tri alphabétique, les pluriels, l'attribut `lang` de la page et les consignes de l'import pour l'IA (l'IA répond dans cette langue ; le JSON ne change pas). Ajouter une langue : un fichier de messages, son entrée dans `MESSAGES` et sa règle de pluriel dans `tn`. Les messages de validation renvoyés par l'API pour un champ sont en anglais (règle du code) : l'interface affiche le message du code d'erreur (`VALIDATION_FAILED`), pas le détail du champ.

**Vérification** : l'interface a été testée dans un vrai navigateur (Edge sans fenêtre, piloté par script) : connexion échouée puis réussie, création, renommage et suppression de livres, erreurs de formulaire, menu d'apparence, modes clair et sombre, les cinq couleurs, mobile avec tiroir, persistance après rechargement, déconnexion. Ces scripts jetables ne sont pas dans le dépôt.

**Écrans prévus** : bibliothèque (liste filtrable par type, recherche par nom ou alias), fiche en édition, chronologie triée par `worldOrder`, édition d'événement avec sélecteur de participants alimenté par `/index`, clés d'API (création, copie unique, suppression, URL MCP prête à copier), **import en masse**.

**Import** (`views/ImportView.vue`, `components/import/`, `utils/importDocument.js`) : l'IA produit un document JSON, on le colle, on le vérifie et on le corrige dans le navigateur, puis on l'envoie d'un coup. Le maximum se passe dans le navigateur, le serveur reste simple.
1. **Consignes** : un texte à copier pour l'IA, **généré dans le navigateur** (`import.instructions.text` dans `fr.js`) à partir des fiches et événements déjà présents (identifiants et noms), pour qu'elle les réutilise sans créer de doublons. Il donne le format exact et les règles.
2. **Coller** la réponse de l'IA. Une clôture ```` ```json ```` autour du document est retirée. Un texte illisible ou un mauvais gabarit (section inconnue, section qui n'est pas une liste…) est signalé en français sous la zone de texte.
3. **Vérifier** : une page liste chaque élément (fiches, événements, participants) avec son statut (nouveau, mise à jour, déjà présent et ignoré) et ses erreurs, **calculées dans le navigateur** (`validateItem` : champs obligatoires, types, format des identifiants, type de fiche, doublons, références vers une fiche ou un événement du livre ou de l'import). Chaque élément peut être décoché, retiré ou corrigé dans un panneau latéral ; un élément qui existe déjà est décoché par défaut. « Importer » reste désactivé tant qu'un élément coché a une erreur. Revenir au texte après des corrections demande confirmation.
4. **Importer** : `POST /api/books/{bookId}/import` avec le document final, **atomique** (une transaction : tout ou rien). Un élément dont l'identifiant existe déjà est mis à jour, les autres sont créés ; les liens sont mis à jour (rôle) ou créés. La réponse donne `created` et `updated` par section.

**Rôle du serveur** (`ImportService`) : il ne détaille pas les erreurs de forme. Un corps qui n'est pas du JSON, une section inconnue, une section qui n'est pas une liste ou un élément qui n'est pas un objet donnent `INVALID_JSON`. Si le document est valide mais que l'écriture échoue (champ invalide, identifiant déjà pris, référence introuvable), l'erreur porte la **raison** et `details.path` (par exemple `events[2]`), et rien n'est écrit. L'interface affiche la raison et surligne l'élément. Les écritures réutilisent les services (`KnowledgeService`, `EventService`, `EventParticipantService`) donc les règles de `Validate`. Limite : 1000 éléments par section.

Format du document (les participants peuvent référencer des éléments du document ou déjà en base) :

```json
{
  "knowledge": [{ "id": "aldric", "type": "character", "name": "Aldric", "summary": "...", "description": null, "aliases": [] }],
  "events": [{ "id": "evt-0043", "title": "...", "summary": "...", "detail": null, "worldOrder": 43, "worldDate": null, "chapter": 8, "revealed": true, "tags": [] }],
  "participants": [{ "eventId": "evt-0043", "knowledgeId": "aldric", "role": "author" }]
}
```

Les champs facultatifs absents prennent leur valeur par défaut (`null`, `[]`, `revealed: true`) dans l'aperçu.

**Export** (`views/ExportView.vue`, `utils/exportDocument.js`) : une page pour télécharger ou copier tout le contenu d'un livre.
- **Une seule route serveur**, `GET /api/books/{bookId}/export` (session ou clé de ce livre) : trois requêtes SQL qui renvoient toutes les fiches (avec description), tous les événements (avec détail) et tous les liens, dans la forme d'un document d'import. Les formats sont fabriqués dans le navigateur.
- **JSON (Codex)** : le document tel quel, que l'import accepte à nouveau (sauvegarde, transfert vers un autre livre).
- **Markdown** : un document lisible, la bibliothèque groupée par type puis la chronologie dans l'ordre du monde, avec identifiants, alias, participants (et leur rôle) et étiquettes. Les libellés suivent la langue de l'interface.
- **Événements secrets** : une case (cochée par défaut) les retire, ainsi que leurs liens, pour ne pas révéler au lecteur ou à une IA ce qui n'est pas encore connu.
- Un aperçu du fichier est affiché ; boutons « Télécharger » (fichier `nom-du-livre-date.json` ou `.md`) et « Copier ».

### Étape 4 : routes pour l'IA (terminée)

Trois lectures pensées pour une IA, accessibles par session ou par la clé du livre (lecture). Les routes CRUD de l'app (`/knowledge`, `/events`…) ne changent pas : l'app est l'auteur et voit tout. Les filtres ci-dessous ne s'appliquent qu'à ces routes.

**Point de vue du lecteur** (paramètres communs à `timeline`, `search` et à la fiche avec `events=true`) :

| Paramètre | Effet |
|---|---|
| `atChapter=N` | le lecteur a lu les chapitres 1 à N : seuls les événements racontés jusqu'au chapitre N |
| `beforeChapter=N` | le lecteur a lu les chapitres 1 à N-1 (on écrit le chapitre N) |
| `includeSecrets=true` | point de vue de l'auteur : ajoute les événements non révélés (`revealed = false`) et ceux qui ne sont jamais racontés (`chapter` vide) |

Sans `includeSecrets`, seuls les événements `revealed = true` sont visibles. Avec un chapitre donné, un événement raconté plus tard n'est jamais visible, même pour l'auteur ; un événement hors-champ (sans chapitre) n'est visible qu'avec `includeSecrets=true`. `atChapter` et `beforeChapter` ensemble donnent `INVALID_QUERY_PARAMETER`. Les fiches (`knowledge`) n'ont pas de chapitre : elles ne sont pas filtrées, mais leur texte peut en dire plus que le point de vue choisi.

| Route | Rôle |
|---|---|
| `GET /api/books/{bookId}/timeline` | événements visibles par ordre du monde (`id`, `title`, `summary`, `worldOrder`, `worldDate`, `chapter`, `revealed`) **avec leurs participants** (`id`, `name`, `role`) ; deux requêtes pour une page. `knowledgeId=` ne garde que les événements d'une fiche ; `limit` / `offset` comme les autres listes |
| `GET /api/books/{bookId}/knowledge/{id}?events=true` | la fiche complète **et** ses événements visibles (avec le rôle de la fiche), au même format paginé que `timeline`, dans `events` ; sans `events=true`, la fiche seule comme avant |
| `GET /api/books/{bookId}/search?q=…` | recherche dans les fiches et les événements visibles, meilleurs résultats d'abord ; `limit` (1 à 50, 20 par défaut) |

**Recherche** : index `FULLTEXT` MySQL (fiches : nom, résumé, description ; événements : titre, résumé, détail) en mode booléen, avec un début de mot accepté (`cita` trouve `citadelle`), plus une comparaison simple sur le nom, l'identifiant, les alias et les étiquettes, mot par mot, pour trouver un surnom ou un mot court. Les mots de moins de 3 lettres ne comptent que s'ils sont seuls. Un résultat dont le nom, l'identifiant, un alias ou une étiquette contient des mots de la recherche passe avant un résultat trouvé seulement dans les textes. Le texte est découpé en mots (lettres et chiffres) : les opérateurs de recherche ne sont pas interprétés. Réponse : `{ data: [{ kind: "knowledge" | "event", id, name, summary, score, … }], total }`.

### Étape 5 : serveur MCP distant (en cours)

**Fait** : une route `/mcp/{clé}` de l'application Symfony, avec le SDK PHP officiel (`mcp/sdk` 0.8, encore en 0.x donc à surveiller) et le transport HTTP « Streamable ». Elle accepte les deux ères du protocole (poignée de main `initialize` avec sessions, et la révision sans état de 2026).

- **Authentification** : la clé du livre fait partie de l'adresse (`https://<hôte>/mcp/cdx_…`). Elle désigne le livre (les outils n'ont donc jamais d'identifiant de livre) et donne l'accès en lecture. Une clé inconnue donne `UNAUTHORIZED` (401). La route est publique pour Symfony (`access_control`) : c'est le contrôleur qui vérifie la clé. La page « Clé d'API » affiche l'adresse MCP à copier. Attention : la clé apparaît dans l'adresse, donc dans les journaux du serveur web ; la régénérer invalide l'ancienne adresse.
- **Outils (lecture seule)** dans `src/Mcp/CodexTools.php`, qui appellent les mêmes services que les routes de l'étape 4 : `index`, `get_knowledge` (fiche et ses événements), `timeline`, `get_event`, `search`. Chacun accepte `atChapter`, `beforeChapter` et `includeSecrets` (voir l'étape 4). Leurs descriptions, en anglais, sont écrites pour l'IA qui les lit. Une erreur est renvoyée à l'IA comme une erreur d'outil lisible (« The event was not found… »). `get_event` applique aussi le point de vue : un événement secret ou raconté plus tard est « introuvable ».
- **Sessions** : stockées en fichiers dans `var/mcp-sessions` (le serveur web doit pouvoir y écrire). Les adresses autorisées dans l'en-tête `Host` (protection contre le DNS rebinding) se règlent avec `MCP_ALLOWED_HOSTS` (liste séparée par des virgules, `localhost,127.0.0.1,[::1]` par défaut) : **en production, y ajouter le nom de domaine public** dans `backend/.env.local`.
- Pas d'outil d'écriture : les ajouts passent par l'import de l'étape 3.

**Reste** : mettre l'application en ligne en HTTPS (ChatGPT n'accepte que des serveurs publics en HTTPS ; un tunnel peut servir de test) et essayer le connecteur dans ChatGPT et Claude. OAuth n'est à ajouter que si Claude refuse la clé dans l'adresse.

**Tester en local** sans IA : `npx @modelcontextprotocol/inspector`, transport « Streamable HTTP », adresse `http://127.0.0.1:8000/mcp/<clé>`.

### Plus tard

Table `relation` bornée dans le temps, recherche sémantique, éventuellement un chat intégré à l'app (il réutiliserait les mêmes outils que le MCP).
