<?php
declare(strict_types=1);

/**
 * The git browser — en locale. Loaded by GitBrowseController via
 * load('GitBrowse'); keys mirror the other locale 1:1 (check_lang_parity.php).
 */
return [
    'gitbrowse.heading' => 'Code',
    'gitbrowse.intro' => 'The repositories on our git server.',
    'gitbrowse.unavailable' => 'The git server is not answering right now. Please try again later.',
    'gitbrowse.not_found' => 'There is no such repository, file or commit.',
    'gitbrowse.no_repos' => 'There are no repositories yet.',
    'gitbrowse.repository' => 'Repository',
    'gitbrowse.description' => 'Description',
    'gitbrowse.updated' => 'Last commit',
    'gitbrowse.code' => 'Code',
    'gitbrowse.log' => 'History',
    'gitbrowse.commits' => 'commits',
    'gitbrowse.branches' => 'Branches',
    'gitbrowse.branch_count' => 'branches',
    'gitbrowse.tags' => 'tags',
    'gitbrowse.clone' => 'Clone',
    'gitbrowse.latest' => 'Latest commit',
    'gitbrowse.size' => 'Size',
    'gitbrowse.lines' => 'lines',
    'gitbrowse.binary' => 'This is a binary file; it is not shown here.',
    'gitbrowse.truncated' => 'Only the beginning is shown here: the file is too long.',
    'gitbrowse.prev' => 'Newer',
    'gitbrowse.next' => 'Older',
    'gitbrowse.author' => 'Author',
    'gitbrowse.date' => 'Date',
    'gitbrowse.parents' => 'Parents',
    'gitbrowse.browse_tree' => 'Browse the files at this commit',
    'gitbrowse.signed' => 'Signed',
    'gitbrowse.tree_truncated' => 'This directory has more entries than are shown here.',

    // Admin → Git server.
    'admin.gitbrowse.heading' => 'Git server',
    'admin.gitbrowse.intro' => 'The git server the Code page shows. Run gitweb from astrx-suite (or any gitweb with its JSON API) and point the site at it.',
    'admin.gitbrowse.status' => 'Connection',
    'admin.gitbrowse.connected' => 'connected, {count} repositories',
    'admin.gitbrowse.unreachable' => 'cannot reach {url}',
    'admin.gitbrowse.api_url' => 'Server address',
    'admin.gitbrowse.api_url_hint' => 'How this site reaches gitweb, e.g. http://gitweb:8801 inside Docker.',
    'admin.gitbrowse.public_url' => 'Public address',
    'admin.gitbrowse.public_url_hint' => 'How visitors reach the same server, for git clone (an onion address, say). Leave empty to hide the clone command.',
    'admin.gitbrowse.auth' => 'Account',
    'admin.gitbrowse.auth_hint' => 'only when gitweb runs with --auth',
    'admin.gitbrowse.auth_user' => 'User',
    'admin.gitbrowse.auth_password' => 'Password',
    'admin.gitbrowse.password_keep' => 'Leave empty to keep the saved password.',
    'admin.gitbrowse.timeout' => 'Timeout',
    'admin.gitbrowse.save' => 'Save',
    'admin.gitbrowse.bad_url' => 'Addresses must be http:// or https:// URLs.',
];
