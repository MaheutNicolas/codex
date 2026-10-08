// Every text shown to the user lives here. Keys and code stay in English, values are French.
export default {
  app: {
    name: 'Codex',
    tagline: 'La bibliothèque de votre histoire',
  },

  common: {
    cancel: 'Annuler',
    save: 'Enregistrer',
    create: 'Créer',
    rename: 'Renommer',
    delete: 'Supprimer',
    close: 'Fermer',
    loading: 'Chargement…',
    required: 'Ce champ est obligatoire.',
  },

  nav: {
    books: 'Mes livres',
    library: 'Bibliothèque',
    timeline: 'Chronologie',
    keys: "Clé d'API",
    import: 'Import',
    export: 'Export',
    logout: 'Se déconnecter',
    openMenu: 'Ouvrir le menu',
    closeMenu: 'Fermer le menu',
    currentBook: 'Livre en cours',
  },

  login: {
    title: 'Connexion',
    subtitle: 'Accédez à votre bibliothèque narrative.',
    username: 'Identifiant',
    password: 'Mot de passe',
    submit: 'Se connecter',
  },

  books: {
    title: 'Mes livres',
    subtitle: 'Chaque livre a sa propre bibliothèque, sa chronologie et ses clés d\'accès.',
    new: 'Nouveau livre',
    createdOn: 'Créé le {date}',
    open: 'Ouvrir {name}',
    empty: {
      title: "Aucun livre pour l'instant",
      text: 'Créez votre premier livre pour commencer à bâtir sa bibliothèque.',
    },
    form: {
      createTitle: 'Nouveau livre',
      renameTitle: 'Renommer le livre',
      name: 'Titre du livre',
      namePlaceholder: 'Ex. Les Chroniques du Nord',
    },
    deleteTitle: 'Supprimer ce livre ?',
    deleteText:
      'Le livre « {name} » et tout son contenu (fiches, événements, clés d\'API) seront supprimés définitivement.',
    created: 'Livre créé.',
    renamed: 'Livre renommé.',
    deleted: 'Livre supprimé.',
  },

  library: {
    title: 'Bibliothèque',
    subtitle: 'Les personnages, groupes, espèces, lieux, objets, systèmes, capacités, concepts, rangs et thèmes de votre histoire.',
    new: 'Nouvelle fiche',
    search: 'Rechercher un nom, un alias…',
    searchLabel: 'Rechercher dans la bibliothèque',
    all: 'Toutes',
    types: {
      character: 'Personnage',
      group: 'Groupe',
      species: 'Espèce',
      place: 'Lieu',
      item: 'Objet',
      system: 'Système',
      ability: 'Capacité',
      concept: 'Concept',
      rank: 'Rang',
      theme: 'Thème',
    },
    typesPlural: {
      character: 'Personnages',
      group: 'Groupes',
      species: 'Espèces',
      place: 'Lieux',
      item: 'Objets',
      system: 'Systèmes',
      ability: 'Capacités',
      concept: 'Concepts',
      rank: 'Rangs',
      theme: 'Thèmes',
    },
    count: { one: '{count} fiche', other: '{count} fiches' },
    aliases: 'Alias : {list}',
    empty: {
      title: "Aucune fiche pour l'instant",
      text: 'Ajoutez votre première fiche (un personnage, un lieu, un système…) : la bibliothèque est ce que l\'IA consultera pour écrire.',
    },
    noResults: {
      title: 'Aucun résultat',
      text: 'Aucune fiche ne correspond à « {query} ».',
      reset: 'Effacer la recherche',
    },
    form: {
      createTitle: 'Nouvelle fiche',
      editTitle: 'Modifier la fiche',
      name: 'Nom',
      namePlaceholder: 'Ex. Aldric',
      type: 'Type',
      id: 'Identifiant',
      idHint: "C'est l'adresse de la fiche, utilisée par l'IA. Il ne pourra plus être modifié.",
      idLocked: "L'identifiant d'une fiche existante ne peut pas être modifié.",
      summary: 'Résumé',
      summaryHint: "2 à 3 phrases : c'est ce que l'IA lit en premier.",
      description: 'Description',
      descriptionHint: 'La version longue, chargée seulement quand elle est utile.',
      aliases: 'Alias',
      aliasesHint: 'Autres noms ou surnoms. Entrée ou virgule pour ajouter.',
      aliasesPlaceholder: 'Ajouter un alias…',
      removeAlias: "Retirer l'alias {alias}",
      saveAndNext: 'Enregistrer et créer une autre',
      errors: {
        nameRequired: 'Le nom est obligatoire.',
        summaryRequired: 'Le résumé est obligatoire.',
        idRequired: "L'identifiant est obligatoire.",
        idFormat: 'Lettres minuscules, chiffres et tirets uniquement (ex. citadelle-nord).',
        idExists: 'Cet identifiant est déjà utilisé dans ce livre.',
      },
    },
    created: 'Fiche créée.',
    saved: 'Fiche enregistrée.',
    deleted: 'Fiche supprimée.',
    deleteTitle: 'Supprimer cette fiche ?',
    deleteText: 'La fiche « {name} » sera supprimée définitivement, ainsi que ses liens avec les événements.',
    discardTitle: 'Abandonner les modifications ?',
    discardText: 'Les changements non enregistrés seront perdus.',
    discard: 'Abandonner',
    keepEditing: 'Continuer la saisie',
  },

  timeline: {
    title: 'Chronologie',
    subtitle: "Les événements de votre histoire, dans l'ordre où ils arrivent dans le monde.",
    new: 'Nouvel événement',
    search: 'Rechercher un titre, un résumé…',
    searchLabel: 'Rechercher dans la chronologie',
    count: { one: '{count} événement', other: '{count} événements' },
    chapterLabel: 'Chapitre',
    allChapters: 'Tous les chapitres',
    offscreenFilter: 'Hors-champ',
    chapter: 'Chapitre {number}',
    offscreen: 'Hors-champ',
    secret: 'Secret',
    visibility: { all: 'Tous', revealed: 'Révélés', secret: 'Secrets' },
    order: 'Ordre {number}',
    moveUp: 'Monter {title}',
    moveDown: 'Descendre {title}',
    empty: {
      title: "Aucun événement pour l'instant",
      text: "Ajoutez le premier événement de votre histoire : la chronologie est ce que l'IA consultera pour ne pas se contredire.",
    },
    noResults: {
      title: 'Aucun résultat',
      text: 'Aucun événement ne correspond à ces critères.',
      reset: 'Effacer les filtres',
    },
    form: {
      createTitle: 'Nouvel événement',
      editTitle: "Modifier l'événement",
      title: 'Titre',
      titlePlaceholder: 'Ex. Le serment',
      id: 'Identifiant',
      idHint: "C'est l'adresse de l'événement, utilisée par l'IA. Il ne pourra plus être modifié.",
      idLocked: "L'identifiant d'un événement existant ne peut pas être modifié.",
      summary: 'Résumé',
      summaryHint: "2 à 3 phrases : c'est ce que l'IA lit en premier.",
      detail: 'Détail',
      detailHint: 'La version longue, chargée seulement quand elle est utile.',
      worldOrder: 'Ordre dans le monde',
      worldOrderHint: "Un nombre entier : l'événement 42 arrive après le 41.",
      worldDate: 'Date dans le monde',
      worldDateHint: 'Facultatif, affichage seul. Ex. An 312, hiver.',
      chapter: 'Chapitre',
      chapterHint: 'Où le lecteur le découvre. Vide : hors-champ.',
      revealed: 'Le lecteur le sait déjà',
      revealedHint: "Décochez pour un événement secret, connu seulement de l'auteur.",
      tags: 'Étiquettes',
      tagsHint: 'Entrée ou virgule pour ajouter.',
      tagsPlaceholder: 'Ajouter une étiquette…',
      participants: 'Participants',
      participantsHint: 'Les fiches concernées par cet événement, avec leur rôle.',
      noParticipants: 'Aucun participant pour cet événement.',
      pick: 'Choisir une fiche…',
      addLabel: 'Ajouter un participant',
      roleLabel: 'Rôle',
      rolePlaceholder: 'Ex. auteur, témoin',
      roleOf: 'Rôle de {name}',
      add: 'Ajouter',
      removeParticipant: 'Retirer {name}',
      unknownEntry: 'Fiche supprimée',
      saveAndNext: 'Enregistrer et créer un autre',
      errors: {
        titleRequired: 'Le titre est obligatoire.',
        summaryRequired: 'Le résumé est obligatoire.',
        idRequired: "L'identifiant est obligatoire.",
        idFormat: 'Lettres minuscules, chiffres et tirets uniquement (ex. evt-0042).',
        idExists: 'Cet identifiant est déjà utilisé dans ce livre.',
        orderInvalid: 'Saisissez un nombre entier.',
        chapterInvalid: 'Saisissez un numéro de chapitre (1 ou plus), ou laissez vide.',
      },
    },
    created: 'Événement créé.',
    saved: 'Événement enregistré.',
    deleted: 'Événement supprimé.',
    participantsFailed: "L'événement est enregistré, mais ses participants n'ont pas pu être tous mis à jour.",
    deleteTitle: 'Supprimer cet événement ?',
    deleteText: "L'événement « {title} » sera supprimé définitivement, ainsi que ses liens avec les fiches.",
    reorderFailed: "L'ordre n'a pas pu être enregistré en entier. La liste a été rechargée.",
    discardTitle: 'Abandonner les modifications ?',
    discardText: 'Les changements non enregistrés seront perdus.',
    discard: 'Abandonner',
    keepEditing: 'Continuer la saisie',
  },

  keys: {
    title: "Clé d'API",
    subtitle: "La clé qui permet à une IA ou à un script de lire ce livre.",
    cardTitle: 'Clé de ce livre',
    cardText: "Cette clé donne un accès en lecture à ce livre, et à lui seul. Elle reste affichée : copiez-la là où vous en avez besoin.",
    tokenLabel: "Clé d'API de ce livre",
    copy: 'Copier',
    copied: 'Clé copiée.',
    copyFailed: "La copie a échoué. Sélectionnez la clé et copiez-la à la main.",
    createdOn: 'Créée le {date}',
    lastUsed: 'Dernière utilisation le {date}',
    neverUsed: 'Jamais utilisée',
    regenerate: 'Régénérer la clé',
    regenerateHint: "À faire si vous pensez que la clé a fuité : l'ancienne est supprimée.",
    regenerated: 'Nouvelle clé générée.',
    mcp: {
      title: 'Adresse du serveur MCP',
      text: "C'est l'adresse à donner à votre IA (ChatGPT, Claude…) pour qu'elle consulte ce livre pendant l'écriture. Elle contient la clé : ne la partagez pas.",
      label: 'Adresse du serveur MCP',
      copied: 'Adresse copiée.',
      hint: "L'IA doit pouvoir joindre le serveur : une adresse locale (localhost) ne fonctionnera qu'une fois l'application en ligne en HTTPS. Régénérer la clé change cette adresse.",
    },
    confirmTitle: 'Régénérer la clé ?',
    confirmText:
      "La clé actuelle cessera de fonctionner immédiatement : tout outil qui l'utilise (une IA, un script) perdra l'accès tant que vous ne lui aurez pas donné la nouvelle clé.",
  },

  import: {
    title: 'Import',
    subtitle: "Faites remplir la bibliothèque et la chronologie par une IA, vérifiez le résultat, puis enregistrez-le.",
    step1: {
      title: "1. Donner les consignes à l'IA",
      text: "Copiez ce texte dans votre IA, avec le passage de votre histoire à analyser. Il contient le format attendu et la liste de ce que le livre contient déjà.",
    },
    step2: {
      title: '2. Coller sa réponse',
      text: "Collez ici le document JSON renvoyé par l'IA. Rien n'est enregistré avant l'étape de vérification.",
      label: "Réponse de l'IA",
      placeholder: '{ "knowledge": [], "events": [], "participants": [] }',
      analyse: 'Vérifier',
    },
    instructions: {
      none: '(aucun pour l’instant)',
      orderOf: 'ordre {number}',
      copied: 'Consignes copiées.',
      text: `Tu m'aides à remplir la bibliothèque d'un livre. À partir du texte que je te donnerai, extrais les personnages, lieux, systèmes et événements, puis réponds UNIQUEMENT avec un document JSON (aucun texte autour), exactement dans ce format :

{
  "knowledge": [
    { "id": "aldric", "type": "character", "name": "Aldric", "summary": "2 à 3 phrases.", "description": "Version longue, ou null.", "aliases": ["le Borgne"] }
  ],
  "events": [
    { "id": "evt-0043", "title": "Le serment", "summary": "2 à 3 phrases.", "detail": null, "worldOrder": 43, "worldDate": "An 312, hiver", "chapter": 8, "revealed": true, "tags": [] }
  ],
  "participants": [
    { "eventId": "evt-0043", "knowledgeId": "aldric", "role": "author" }
  ]
}

Règles :
- "id" : lettres minuscules sans accent, chiffres et tirets uniquement (ex. citadelle-nord). Il est unique.
- "type" d'une fiche : un parmi {types}.
- Types de fiches :
  - "character" : une personne ou un être doté de volonté.
  - "group" : une organisation, faction, famille, guilde, peuple.
  - "species" : une espèce ou une race d'êtres.
  - "place" : un lieu, de la pièce au continent.
  - "item" : un objet, une arme, un artefact.
  - "system" : les règles d'un fonctionnement (magie, technologie, société…).
  - "ability" : un pouvoir, une compétence, une technique.
  - "concept" : une idée, croyance, loi ou notion propre à cet univers.
  - "rank" : un titre, grade ou classe dans une hiérarchie.
  - "theme" : un thème ou motif récurrent de l'histoire.
- En cas de doute entre deux types, choisis le plus précis.
- "summary" : 2 à 3 phrases, c'est ce que l'IA lit en premier. "description" et "detail" : la version longue, ou null.
- "worldOrder" : nombre entier qui donne l'ordre des événements dans le monde de l'histoire (le plus petit arrive en premier). Le prochain ordre libre est {nextOrder}.
- "chapter" : numéro du chapitre où le lecteur découvre l'événement (entier, 1 ou plus), ou null s'il n'est pas raconté.
- "revealed" : false si le lecteur ne sait pas encore ce qui s'est passé.
- "participants" relie un événement à une fiche ("eventId" et "knowledgeId" sont des "id"), avec un "role" court (author, victim, witness, place…) ou null.
- Utilise null quand une information est inconnue. N'invente rien.
- Ces éléments existent déjà : ne les renvoie pas, sauf pour les corriger avec le même "id". Tu peux t'y référer dans "participants".

Fiches déjà présentes :
{knowledge}

Événements déjà présents :
{events}`,
    },
    parse: {
      invalidJson: "Ce texte n'est pas du JSON valide ({reason}). Copiez bien toute la réponse de l'IA.",
      notObject: 'Le document doit être un objet JSON avec les sections « knowledge », « events » et « participants ».',
      unknownSection: 'Section inconnue : « {section} ». Les sections possibles sont « knowledge », « events » et « participants ».',
      notList: 'La section « {section} » doit être une liste.',
      tooMany: 'La section « {section} » contient trop d’éléments (maximum {max}).',
      notItem: 'L’élément {number} de « {section} » doit être un objet.',
      empty: 'Le document ne contient aucun élément.',
    },
    sections: { knowledge: 'Fiches', events: 'Événements', participants: 'Participants' },
    status: {
      new: 'Nouveau',
      update: 'Mise à jour',
      existsIgnored: 'Existe déjà · ignoré',
      ignored: 'Ignoré',
    },
    review: {
      back: 'Modifier le texte',
      backTitle: 'Revenir au texte ?',
      backText: 'Les corrections faites dans cette vérification seront perdues.',
      summary: '{created} à créer, {updated} à mettre à jour, {links} liens, {ignored} ignorés.',
      errors: 'À corriger : {count}.',
      submit: 'Importer',
      help: "Décochez ce que vous ne voulez pas importer, modifiez ce qui doit l'être. Un élément qui existe déjà est ignoré par défaut : cochez-le pour le mettre à jour.",
      include: 'Importer {title}',
      edit: 'Modifier {title}',
      remove: 'Retirer {title}',
      roleIs: 'Rôle : {role}',
    },
    edit: {
      title: "Corriger l'élément",
      apply: 'Appliquer',
      eventId: "Identifiant de l'événement",
      eventIdHint: "Un événement du livre ou de cet import.",
      knowledgeId: 'Identifiant de la fiche',
      knowledgeIdHint: 'Une fiche du livre ou de cet import.',
    },
    errors: {
      required: 'Obligatoire.',
      string: 'Doit être un texte.',
      tooLong: 'Maximum {max} caractères.',
      slug: 'Lettres minuscules, chiffres et tirets uniquement (ex. citadelle-nord).',
      knowledgeType: 'Type inconnu. Types possibles : {types}.',
      integer: 'Doit être un nombre entier.',
      chapter: 'Doit être un numéro de chapitre (1 ou plus), ou vide.',
      boolean: 'Doit valoir true ou false.',
      list: 'Doit être une liste de textes.',
      unknownField: 'Champ inconnu : il sera retiré si vous corrigez l’élément.',
      unknownEvent: "Aucun événement avec cet identifiant, ni dans le livre ni dans l'import.",
      unknownKnowledge: "Aucune fiche avec cet identifiant, ni dans le livre ni dans l'import.",
      duplicate: 'Cet élément apparaît plusieurs fois dans l’import.',
    },
    server: {
      title: "L'import a été refusé par le serveur.",
      nothingSaved: "Rien n'a été enregistré : corrigez le problème puis réessayez.",
      item: '{section} : « {title} »',
      invalidJson: 'Le serveur a jugé le document invalide.',
      reference: 'Le champ {field} pointe vers « {id} », qui n’existe pas.',
    },
    done: {
      title: 'Import terminé',
      text: '{created} élément(s) créé(s), {updated} mis à jour.',
      again: 'Nouvel import',
    },
  },

  export: {
    title: 'Export',
    subtitle: 'Téléchargez tout le contenu de ce livre dans le format de votre choix.',
    format: 'Format',
    formats: {
      json: {
        name: 'JSON (Codex)',
        text: 'Le format de Codex, identique à celui de l’import : il sert de sauvegarde et peut être réimporté tel quel.',
      },
      markdown: {
        name: 'Markdown',
        text: 'Un document lisible : la bibliothèque par type, puis la chronologie. Pratique à coller dans une IA, un wiki ou un éditeur de notes.',
      },
    },
    secrets: 'Inclure les événements secrets',
    secretsHint: 'Décochez pour ne pas révéler ce que le lecteur ne sait pas encore, par exemple avant de donner le document à une IA.',
    counts: 'Fiches : {knowledge} · Événements : {events} · Liens : {links}',
    download: 'Télécharger',
    copied: 'Contenu copié.',
    empty: {
      title: 'Rien à exporter pour l’instant',
      text: 'Ajoutez des fiches ou des événements, ou importez-en, puis revenez ici.',
    },
    md: {
      library: 'Bibliothèque',
      timeline: 'Chronologie',
      id: 'identifiant : {id}',
      participants: 'Participants :',
      tags: 'Étiquettes :',
    },
  },

  theme: {
    title: 'Apparence',
    mode: 'Mode',
    accent: 'Couleur',
    language: 'Langue',
    modes: { system: 'Système', light: 'Clair', dark: 'Sombre' },
    accents: { indigo: 'Indigo', teal: 'Sarcelle', green: 'Vert', amber: 'Ambre', rose: 'Rose' },
  },

  // One message per error code of the API (see GET /api/errors). Unknown codes fall back to the API message.
  errors: {
    NETWORK_ERROR: 'Impossible de joindre le serveur. Vérifiez votre connexion et que le serveur est démarré.',
    INVALID_JSON: 'La requête envoyée est illisible.',
    VALIDATION_FAILED: 'Certaines informations sont invalides. Corrigez les champs signalés.',
    INVALID_QUERY_PARAMETER: 'Un paramètre de la requête est invalide.',
    UNAUTHORIZED: 'Votre session a expiré. Reconnectez-vous.',
    LOGIN_FAILED: 'Identifiant ou mot de passe incorrect.',
    FORBIDDEN: "Cette action n'est pas autorisée avec vos accès actuels.",
    ROUTE_NOT_FOUND: "Cette page du serveur n'existe pas.",
    BOOK_NOT_FOUND: 'Ce livre est introuvable.',
    KNOWLEDGE_NOT_FOUND: 'Cette fiche est introuvable.',
    EVENT_NOT_FOUND: 'Cet événement est introuvable.',
    PARTICIPANT_NOT_FOUND: "Ce lien entre l'événement et la fiche n'existe pas.",
    RELATION_NOT_FOUND: 'Cette relation est introuvable.',
    METHOD_NOT_ALLOWED: "Cette opération n'est pas permise ici.",
    ID_ALREADY_EXISTS: 'Cet identifiant existe déjà.',
    RELATION_ALREADY_EXISTS: 'Ces deux fiches ont déjà un état de relation à ce chapitre.',
    REFERENCE_NOT_FOUND: "Un élément référencé n'existe pas.",
    TOO_MANY_ATTEMPTS: 'Trop de tentatives de connexion. Patientez quelques minutes avant de réessayer.',
    INTERNAL_ERROR: 'Une erreur est survenue sur le serveur. Réessayez dans un instant.',
  },
}
