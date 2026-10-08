// Every text shown to the user, in English. It must have exactly the same keys as fr.js.
export default {
  app: {
    name: 'Codex',
    tagline: 'The library of your story',
  },

  common: {
    cancel: 'Cancel',
    save: 'Save',
    create: 'Create',
    rename: 'Rename',
    delete: 'Delete',
    close: 'Close',
    loading: 'Loading…',
    required: 'This field is required.',
  },

  nav: {
    books: 'My books',
    library: 'Library',
    timeline: 'Timeline',
    keys: 'API key',
    import: 'Import',
    export: 'Export',
    logout: 'Log out',
    openMenu: 'Open the menu',
    closeMenu: 'Close the menu',
    currentBook: 'Current book',
  },

  login: {
    title: 'Log in',
    subtitle: 'Access your narrative library.',
    username: 'Username',
    password: 'Password',
    submit: 'Log in',
  },

  books: {
    title: 'My books',
    subtitle: 'Each book has its own library, timeline and access key.',
    new: 'New book',
    createdOn: 'Created on {date}',
    open: 'Open {name}',
    empty: {
      title: 'No books yet',
      text: 'Create your first book to start building its library.',
    },
    form: {
      createTitle: 'New book',
      renameTitle: 'Rename the book',
      name: 'Book title',
      namePlaceholder: 'E.g. The Northern Chronicles',
    },
    deleteTitle: 'Delete this book?',
    deleteText: 'The book "{name}" and all its content (entries, events, API key) will be permanently deleted.',
    created: 'Book created.',
    renamed: 'Book renamed.',
    deleted: 'Book deleted.',
  },

  library: {
    title: 'Library',
    subtitle: 'The characters, groups, species, places, items, systems, abilities, concepts, ranks and themes of your story.',
    new: 'New entry',
    search: 'Search a name, an alias…',
    searchLabel: 'Search the library',
    all: 'All',
    types: {
      character: 'Character',
      group: 'Group',
      species: 'Species',
      place: 'Place',
      item: 'Item',
      system: 'System',
      ability: 'Ability',
      concept: 'Concept',
      rank: 'Rank',
      theme: 'Theme',
    },
    typesPlural: {
      character: 'Characters',
      group: 'Groups',
      species: 'Species',
      place: 'Places',
      item: 'Items',
      system: 'Systems',
      ability: 'Abilities',
      concept: 'Concepts',
      rank: 'Ranks',
      theme: 'Themes',
    },
    count: { one: '{count} entry', other: '{count} entries' },
    aliases: 'Aliases: {list}',
    empty: {
      title: 'No entries yet',
      text: 'Add your first entry (a character, a place, a system…): the library is what the AI will consult to write.',
    },
    noResults: {
      title: 'No results',
      text: 'No entry matches "{query}".',
      reset: 'Clear the search',
    },
    form: {
      createTitle: 'New entry',
      editTitle: 'Edit the entry',
      name: 'Name',
      namePlaceholder: 'E.g. Aldric',
      type: 'Type',
      id: 'Identifier',
      idHint: 'This is the address of the entry, used by the AI. It cannot be changed afterwards.',
      idLocked: 'The identifier of an existing entry cannot be changed.',
      summary: 'Summary',
      summaryHint: '2 to 3 sentences: this is what the AI reads first.',
      description: 'Description',
      descriptionHint: 'The long version, loaded only when it is useful.',
      aliases: 'Aliases',
      aliasesHint: 'Other names or nicknames. Enter or comma to add.',
      aliasesPlaceholder: 'Add an alias…',
      removeAlias: 'Remove the alias {alias}',
      saveAndNext: 'Save and create another',
      errors: {
        nameRequired: 'The name is required.',
        summaryRequired: 'The summary is required.',
        idRequired: 'The identifier is required.',
        idFormat: 'Lowercase letters, digits and hyphens only (e.g. north-citadel).',
        idExists: 'This identifier is already used in this book.',
      },
    },
    created: 'Entry created.',
    saved: 'Entry saved.',
    deleted: 'Entry deleted.',
    deleteTitle: 'Delete this entry?',
    deleteText: 'The entry "{name}" will be permanently deleted, along with its links to events.',
    discardTitle: 'Discard the changes?',
    discardText: 'Unsaved changes will be lost.',
    discard: 'Discard',
    keepEditing: 'Keep editing',
  },

  timeline: {
    title: 'Timeline',
    subtitle: 'The events of your story, in the order they happen in the world.',
    new: 'New event',
    search: 'Search a title, a summary…',
    searchLabel: 'Search the timeline',
    count: { one: '{count} event', other: '{count} events' },
    chapterLabel: 'Chapter',
    allChapters: 'All chapters',
    offscreenFilter: 'Off-screen',
    chapter: 'Chapter {number}',
    offscreen: 'Off-screen',
    secret: 'Secret',
    visibility: { all: 'All', revealed: 'Revealed', secret: 'Secret' },
    order: 'Order {number}',
    moveUp: 'Move {title} up',
    moveDown: 'Move {title} down',
    empty: {
      title: 'No events yet',
      text: 'Add the first event of your story: the timeline is what the AI will consult to avoid contradicting itself.',
    },
    noResults: {
      title: 'No results',
      text: 'No event matches these criteria.',
      reset: 'Clear the filters',
    },
    form: {
      createTitle: 'New event',
      editTitle: 'Edit the event',
      title: 'Title',
      titlePlaceholder: 'E.g. The oath',
      id: 'Identifier',
      idHint: 'This is the address of the event, used by the AI. It cannot be changed afterwards.',
      idLocked: 'The identifier of an existing event cannot be changed.',
      summary: 'Summary',
      summaryHint: '2 to 3 sentences: this is what the AI reads first.',
      detail: 'Detail',
      detailHint: 'The long version, loaded only when it is useful.',
      worldOrder: 'World order',
      worldOrderHint: 'A whole number: event 42 happens after event 41.',
      worldDate: 'World date',
      worldDateHint: 'Optional, display only. E.g. Year 312, winter.',
      chapter: 'Chapter',
      chapterHint: 'Where the reader discovers it. Empty: off-screen.',
      revealed: 'The reader already knows',
      revealedHint: 'Untick for a secret event, known only to the author.',
      tags: 'Tags',
      tagsHint: 'Enter or comma to add.',
      tagsPlaceholder: 'Add a tag…',
      participants: 'Participants',
      participantsHint: 'The entries involved in this event, with their role.',
      noParticipants: 'No participants for this event.',
      pick: 'Choose an entry…',
      addLabel: 'Add a participant',
      roleLabel: 'Role',
      rolePlaceholder: 'E.g. author, witness',
      roleOf: 'Role of {name}',
      add: 'Add',
      removeParticipant: 'Remove {name}',
      unknownEntry: 'Deleted entry',
      saveAndNext: 'Save and create another',
      errors: {
        titleRequired: 'The title is required.',
        summaryRequired: 'The summary is required.',
        idRequired: 'The identifier is required.',
        idFormat: 'Lowercase letters, digits and hyphens only (e.g. evt-0042).',
        idExists: 'This identifier is already used in this book.',
        orderInvalid: 'Enter a whole number.',
        chapterInvalid: 'Enter a chapter number (1 or more), or leave empty.',
      },
    },
    created: 'Event created.',
    saved: 'Event saved.',
    deleted: 'Event deleted.',
    participantsFailed: 'The event is saved, but not all of its participants could be updated.',
    deleteTitle: 'Delete this event?',
    deleteText: 'The event "{title}" will be permanently deleted, along with its links to entries.',
    reorderFailed: 'The order could not be fully saved. The list has been reloaded.',
    discardTitle: 'Discard the changes?',
    discardText: 'Unsaved changes will be lost.',
    discard: 'Discard',
    keepEditing: 'Keep editing',
  },

  keys: {
    title: 'API key',
    subtitle: 'The key that lets an AI or a script read this book.',
    cardTitle: 'Key of this book',
    cardText: 'This key gives read access to this book, and to this book only. It stays visible: copy it wherever you need it.',
    tokenLabel: 'API key of this book',
    copy: 'Copy',
    copied: 'Key copied.',
    copyFailed: 'Copying failed. Select the key and copy it by hand.',
    createdOn: 'Created on {date}',
    lastUsed: 'Last used on {date}',
    neverUsed: 'Never used',
    regenerate: 'Regenerate the key',
    regenerateHint: 'Do this if you think the key has leaked: the old one is deleted.',
    regenerated: 'New key generated.',
    mcp: {
      title: 'MCP server address',
      text: 'This is the address to give your AI (ChatGPT, Claude…) so that it can consult this book while writing. It contains the key: do not share it.',
      label: 'MCP server address',
      copied: 'Address copied.',
      hint: 'The AI must be able to reach the server: a local address (localhost) will only work once the app is online over HTTPS. Regenerating the key changes this address.',
    },
    confirmTitle: 'Regenerate the key?',
    confirmText:
      'The current key will stop working immediately: any tool using it (an AI, a script) will lose access until you give it the new key.',
  },

  import: {
    title: 'Import',
    subtitle: 'Have an AI fill the library and the timeline, check the result, then save it.',
    step1: {
      title: '1. Give the instructions to the AI',
      text: 'Copy this text into your AI, together with the passage of your story to analyse. It contains the expected format and the list of what the book already holds.',
    },
    step2: {
      title: '2. Paste its answer',
      text: 'Paste the JSON document returned by the AI here. Nothing is saved before the review step.',
      label: 'Answer of the AI',
      placeholder: '{ "knowledge": [], "events": [], "participants": [] }',
      analyse: 'Check',
    },
    instructions: {
      none: '(none yet)',
      orderOf: 'order {number}',
      copied: 'Instructions copied.',
      text: `You are helping me fill the library of a book. From the text I will give you, extract the characters, places, systems and events, then answer ONLY with a JSON document (no text around it), exactly in this format:

{
  "knowledge": [
    { "id": "aldric", "type": "character", "name": "Aldric", "summary": "2 to 3 sentences.", "description": "Long version, or null.", "aliases": ["the One-Eyed"] }
  ],
  "events": [
    { "id": "evt-0043", "title": "The oath", "summary": "2 to 3 sentences.", "detail": null, "worldOrder": 43, "worldDate": "Year 312, winter", "chapter": 8, "revealed": true, "tags": [] }
  ],
  "participants": [
    { "eventId": "evt-0043", "knowledgeId": "aldric", "role": "author" }
  ]
}

Rules:
- "id": lowercase letters without accents, digits and hyphens only (e.g. north-citadel). It is unique.
- "type" of an entry: one of {types}.
- Entry types:
  - "character": a person or a being with a will of its own.
  - "group": an organisation, faction, family, guild or people.
  - "species": a species or race of beings.
  - "place": a place, from a room to a continent.
  - "item": an object, weapon or artefact.
  - "system": the rules of how something works (magic, technology, society…).
  - "ability": a power, skill or technique.
  - "concept": an idea, belief, law or notion specific to this universe.
  - "rank": a title, grade or class in a hierarchy.
  - "theme": a recurring theme or motif of the story.
- When two types fit, pick the more precise one.
- "summary": 2 to 3 sentences, this is what the AI reads first. "description" and "detail": the long version, or null.
- "worldOrder": a whole number giving the order of the events in the world of the story (the smallest happens first). The next free order is {nextOrder}.
- "chapter": number of the chapter where the reader discovers the event (a whole number, 1 or more), or null if it is not told.
- "revealed": false if the reader does not know yet what happened.
- "participants" links an event to an entry ("eventId" and "knowledgeId" are "id" values), with a short "role" (author, victim, witness, place…) or null.
- Use null when a piece of information is unknown. Do not invent anything.
- These items already exist: do not send them again, except to correct them with the same "id". You can refer to them in "participants".

Entries already present:
{knowledge}

Events already present:
{events}`,
    },
    parse: {
      invalidJson: 'This text is not valid JSON ({reason}). Make sure you copied the whole answer of the AI.',
      notObject: 'The document must be a JSON object with the sections "knowledge", "events" and "participants".',
      unknownSection: 'Unknown section: "{section}". The possible sections are "knowledge", "events" and "participants".',
      notList: 'The section "{section}" must be a list.',
      tooMany: 'The section "{section}" contains too many items (maximum {max}).',
      notItem: 'Item {number} of "{section}" must be an object.',
      empty: 'The document contains no items.',
    },
    sections: { knowledge: 'Entries', events: 'Events', participants: 'Participants' },
    status: {
      new: 'New',
      update: 'Update',
      existsIgnored: 'Already exists · ignored',
      ignored: 'Ignored',
    },
    review: {
      back: 'Edit the text',
      backTitle: 'Go back to the text?',
      backText: 'The corrections made in this review will be lost.',
      summary: '{created} to create, {updated} to update, {links} links, {ignored} ignored.',
      errors: 'To fix: {count}.',
      submit: 'Import',
      help: 'Untick what you do not want to import, edit what needs it. An item that already exists is ignored by default: tick it to update it.',
      include: 'Import {title}',
      edit: 'Edit {title}',
      remove: 'Remove {title}',
      roleIs: 'Role: {role}',
    },
    edit: {
      title: 'Fix the item',
      apply: 'Apply',
      eventId: 'Event identifier',
      eventIdHint: 'An event of the book or of this import.',
      knowledgeId: 'Entry identifier',
      knowledgeIdHint: 'An entry of the book or of this import.',
    },
    errors: {
      required: 'Required.',
      string: 'Must be a text.',
      tooLong: 'Maximum {max} characters.',
      slug: 'Lowercase letters, digits and hyphens only (e.g. north-citadel).',
      knowledgeType: 'Unknown type. Possible types: {types}.',
      integer: 'Must be a whole number.',
      chapter: 'Must be a chapter number (1 or more), or empty.',
      boolean: 'Must be true or false.',
      list: 'Must be a list of texts.',
      unknownField: 'Unknown field: it will be removed if you fix the item.',
      unknownEvent: 'No event with this identifier, neither in the book nor in the import.',
      unknownKnowledge: 'No entry with this identifier, neither in the book nor in the import.',
      duplicate: 'This item appears several times in the import.',
    },
    server: {
      title: 'The import was refused by the server.',
      nothingSaved: 'Nothing was saved: fix the problem and try again.',
      item: '{section}: "{title}"',
      invalidJson: 'The server found the document invalid.',
      reference: 'The field {field} points to "{id}", which does not exist.',
    },
    done: {
      title: 'Import complete',
      text: '{created} item(s) created, {updated} updated.',
      again: 'New import',
    },
  },

  export: {
    title: 'Export',
    subtitle: 'Download everything this book holds, in the format of your choice.',
    format: 'Format',
    formats: {
      json: {
        name: 'JSON (Codex)',
        text: 'The Codex format, the same as the import: it works as a backup and can be imported again as is.',
      },
      markdown: {
        name: 'Markdown',
        text: 'A readable document: the library by type, then the timeline. Handy to paste into an AI, a wiki or a notes editor.',
      },
    },
    secrets: 'Include secret events',
    secretsHint: 'Untick so as not to reveal what the reader does not know yet, for instance before giving the document to an AI.',
    counts: 'Entries: {knowledge} · Events: {events} · Links: {links}',
    download: 'Download',
    copied: 'Content copied.',
    empty: {
      title: 'Nothing to export yet',
      text: 'Add entries or events, or import some, then come back here.',
    },
    md: {
      library: 'Library',
      timeline: 'Timeline',
      id: 'identifier: {id}',
      participants: 'Participants:',
      tags: 'Tags:',
    },
  },

  theme: {
    title: 'Appearance',
    mode: 'Mode',
    accent: 'Color',
    language: 'Language',
    modes: { system: 'System', light: 'Light', dark: 'Dark' },
    accents: { indigo: 'Indigo', teal: 'Teal', green: 'Green', amber: 'Amber', rose: 'Rose' },
  },

  // One message per error code of the API (see GET /api/errors). Unknown codes fall back to the API message.
  errors: {
    NETWORK_ERROR: 'The server cannot be reached. Check your connection and that the server is running.',
    INVALID_JSON: 'The request sent is unreadable.',
    VALIDATION_FAILED: 'Some information is invalid. Fix the highlighted fields.',
    INVALID_QUERY_PARAMETER: 'A parameter of the request is invalid.',
    UNAUTHORIZED: 'Your session has expired. Log in again.',
    LOGIN_FAILED: 'Incorrect username or password.',
    FORBIDDEN: 'This action is not allowed with your current access.',
    ROUTE_NOT_FOUND: 'This page of the server does not exist.',
    BOOK_NOT_FOUND: 'This book cannot be found.',
    KNOWLEDGE_NOT_FOUND: 'This entry cannot be found.',
    EVENT_NOT_FOUND: 'This event cannot be found.',
    PARTICIPANT_NOT_FOUND: 'This link between the event and the entry does not exist.',
    METHOD_NOT_ALLOWED: 'This operation is not allowed here.',
    ID_ALREADY_EXISTS: 'This identifier already exists.',
    REFERENCE_NOT_FOUND: 'A referenced item does not exist.',
    TOO_MANY_ATTEMPTS: 'Too many login attempts. Wait a few minutes before trying again.',
    INTERNAL_ERROR: 'An error occurred on the server. Try again in a moment.',
  },
}
