<?php
declare(strict_types=1);

/**
 * The git browser — it locale. Loaded by GitBrowseController via
 * load('GitBrowse'); keys mirror the other locale 1:1 (check_lang_parity.php).
 */
return [
    'gitbrowse.heading' => 'Codice',
    'gitbrowse.intro' => 'I repository del nostro server git.',
    'gitbrowse.unavailable' => 'Il server git non risponde al momento. Riprova più tardi.',
    'gitbrowse.not_found' => 'Questo repository, file o commit non esiste.',
    'gitbrowse.no_repos' => 'Non ci sono ancora repository.',
    'gitbrowse.repository' => 'Repository',
    'gitbrowse.description' => 'Descrizione',
    'gitbrowse.updated' => 'Ultimo commit',
    'gitbrowse.code' => 'Codice',
    'gitbrowse.log' => 'Cronologia',
    'gitbrowse.commits' => 'commit',
    'gitbrowse.branches' => 'Branch',
    'gitbrowse.branch_count' => 'branch',
    'gitbrowse.tags' => 'tag',
    'gitbrowse.clone' => 'Clona',
    'gitbrowse.latest' => 'Ultimo commit',
    'gitbrowse.size' => 'Dimensione',
    'gitbrowse.lines' => 'righe',
    'gitbrowse.binary' => 'È un file binario: non viene mostrato qui.',
    'gitbrowse.truncated' => 'Qui è mostrato solo l\'inizio: il file è troppo lungo.',
    'gitbrowse.prev' => 'Più recenti',
    'gitbrowse.next' => 'Meno recenti',
    'gitbrowse.author' => 'Autore',
    'gitbrowse.date' => 'Data',
    'gitbrowse.parents' => 'Genitori',
    'gitbrowse.browse_tree' => 'Sfoglia i file a questo commit',
    'gitbrowse.signed' => 'Firmato',
    'gitbrowse.tree_truncated' => 'Questa cartella ha più voci di quelle mostrate qui.',

    // Admin → Git server.
    'admin.gitbrowse.heading' => 'Server git',
    'admin.gitbrowse.intro' => 'Il server git mostrato nella pagina Codice. Avvia gitweb da astrx-suite (o qualsiasi gitweb con la sua API JSON) e collega il sito.',
    'admin.gitbrowse.status' => 'Connessione',
    'admin.gitbrowse.connected' => 'connesso, {count} repository',
    'admin.gitbrowse.unreachable' => 'impossibile raggiungere {url}',
    'admin.gitbrowse.api_url' => 'Indirizzo del server',
    'admin.gitbrowse.api_url_hint' => 'Come questo sito raggiunge gitweb, es. http://gitweb:8801 in Docker.',
    'admin.gitbrowse.public_url' => 'Indirizzo pubblico',
    'admin.gitbrowse.public_url_hint' => 'Come i visitatori raggiungono lo stesso server, per git clone (ad esempio un indirizzo onion). Lascia vuoto per nascondere il comando clone.',
    'admin.gitbrowse.auth' => 'Account',
    'admin.gitbrowse.auth_hint' => 'solo se gitweb usa --auth',
    'admin.gitbrowse.auth_user' => 'Utente',
    'admin.gitbrowse.auth_password' => 'Password',
    'admin.gitbrowse.password_keep' => 'Lascia vuoto per mantenere la password salvata.',
    'admin.gitbrowse.timeout' => 'Timeout',
    'admin.gitbrowse.save' => 'Salva',
    'admin.gitbrowse.bad_url' => 'Gli indirizzi devono essere URL http:// o https://.',
];
